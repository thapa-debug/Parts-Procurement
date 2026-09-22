<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\WebhookSignature;

uses(RefreshDatabase::class);

const STRIPE_WEBHOOK_TEST_SECRET = 'whsec_test_secret';

/**
 * @param  array<string, mixed>  $overrides  merged onto the PaymentIntent object
 */
function stripeEventPayload(string $type, array $overrides = []): string
{
    return json_encode([
        'id' => 'evt_test_'.uniqid(),
        'object' => 'event',
        'type' => $type,
        'data' => [
            'object' => array_merge([
                'id' => 'pi_test_default',
                'object' => 'payment_intent',
                'status' => 'succeeded',
            ], $overrides),
        ],
    ]);
}

/**
 * Posts a webhook payload with a genuinely valid Stripe-Signature header
 * for the configured test secret -- proves the *handling*, not the
 * signature check itself (see the "forged" tests below for that).
 */
function postSignedStripeWebhook(string $payload)
{
    $signature = WebhookSignature::generateSignatureHeader($payload, STRIPE_WEBHOOK_TEST_SECRET);

    return test()->call(
        'POST',
        route('webhooks.stripe'),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature],
        $payload,
    );
}

beforeEach(function () {
    config(['services.stripe.webhook_secret' => STRIPE_WEBHOOK_TEST_SECRET]);
});

it('confirms the matching payment when it receives a genuinely signed payment_intent.succeeded event', function () {
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_test_success',
        'status' => PaymentStatus::Pending,
    ]);

    $payload = stripeEventPayload('payment_intent.succeeded', ['id' => 'pi_test_success', 'status' => 'succeeded']);

    postSignedStripeWebhook($payload)
        ->assertOk()
        ->assertJson(['received' => true]);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Confirmed);
});

it('fails the matching payment when it receives a genuinely signed payment_intent.payment_failed event', function () {
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_test_failure',
        'status' => PaymentStatus::Pending,
    ]);

    $payload = stripeEventPayload('payment_intent.payment_failed', ['id' => 'pi_test_failure', 'status' => 'requires_payment_method']);

    postSignedStripeWebhook($payload)->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed);
});

it('acknowledges, but ignores, an event type it does not handle', function () {
    $payload = stripeEventPayload('charge.dispute.created', ['id' => 'pi_irrelevant']);

    postSignedStripeWebhook($payload)
        ->assertOk()
        ->assertJson(['received' => true]);
});

it('is idempotent -- redelivering the same succeeded event twice never errors', function () {
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_test_redelivered',
        'status' => PaymentStatus::Pending,
    ]);

    $payload = stripeEventPayload('payment_intent.succeeded', ['id' => 'pi_test_redelivered']);

    postSignedStripeWebhook($payload)->assertOk();
    postSignedStripeWebhook($payload)->assertOk();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Confirmed);
});

// --- the money-critical part: a forged/invalid request must be rejected ---

it('rejects a payload with no Stripe-Signature header at all', function () {
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_test_forged_1',
        'status' => PaymentStatus::Pending,
    ]);

    $payload = stripeEventPayload('payment_intent.succeeded', ['id' => 'pi_test_forged_1']);

    test()->call(
        'POST',
        route('webhooks.stripe'),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        $payload,
    )->assertStatus(400);

    // Nothing was confirmed -- the whole point of signature verification.
    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects a payload signed with the wrong secret -- a forged POST pretending to be Stripe', function () {
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_test_forged_2',
        'status' => PaymentStatus::Pending,
    ]);

    $payload = stripeEventPayload('payment_intent.succeeded', ['id' => 'pi_test_forged_2']);
    $forgedSignature = WebhookSignature::generateSignatureHeader($payload, 'whsec_an_attackers_guess');

    test()->call(
        'POST',
        route('webhooks.stripe'),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $forgedSignature],
        $payload,
    )->assertStatus(400);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects a genuinely-signed payload that was then tampered with after signing', function () {
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_test_forged_3',
        'status' => PaymentStatus::Pending,
    ]);

    $originalPayload = stripeEventPayload('payment_intent.succeeded', ['id' => 'pi_test_forged_3']);
    $signature = WebhookSignature::generateSignatureHeader($originalPayload, STRIPE_WEBHOOK_TEST_SECRET);

    // The signature was computed over $originalPayload; sending a
    // different body with that same header must not verify.
    $tamperedPayload = stripeEventPayload('payment_intent.succeeded', ['id' => 'pi_test_forged_3', 'status' => 'succeeded', 'amount' => 999_999_999]);

    test()->call(
        'POST',
        route('webhooks.stripe'),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature],
        $tamperedPayload,
    )->assertStatus(400);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects a malformed (non-JSON) body even with a syntactically-shaped signature header', function () {
    $payload = 'not valid json at all';
    $signature = WebhookSignature::generateSignatureHeader($payload, STRIPE_WEBHOOK_TEST_SECRET);

    test()->call(
        'POST',
        route('webhooks.stripe'),
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature],
        $payload,
    )->assertStatus(400);
});

it('does not require CSRF verification -- Stripe\'s server has no session or token to send', function () {
    // If the CSRF exemption in bootstrap/app.php were missing or wrong,
    // every request in this file would 419 instead of reaching the
    // controller at all. This test just makes that guarantee explicit.
    $payload = stripeEventPayload('payment_intent.succeeded', ['id' => 'pi_test_csrf_check']);

    postSignedStripeWebhook($payload)->assertStatus(200);
});
