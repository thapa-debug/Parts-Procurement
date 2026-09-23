<?php

use App\Actions\ConfirmStripePaymentAction;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmedAdminNotification;
use App\Notifications\PaymentConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('marks a pending stripe payment confirmed', function () {
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_test_123',
        'status' => PaymentStatus::Pending,
    ]);

    app(ConfirmStripePaymentAction::class)->execute('pi_test_123');

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->paid_at)->not->toBeNull();
});

it('is idempotent -- running it twice for the same PaymentIntent does not error or change paid_at again', function () {
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_test_456',
        'status' => PaymentStatus::Pending,
    ]);

    app(ConfirmStripePaymentAction::class)->execute('pi_test_456');
    $firstPaidAt = $payment->refresh()->paid_at;

    // Simulates Stripe redelivering the same webhook event.
    app(ConfirmStripePaymentAction::class)->execute('pi_test_456');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($payment->paid_at->eq($firstPaidAt))->toBeTrue();
});

it('does not send a duplicate payment-confirmed notification when a webhook is redelivered', function () {
    Notification::fake();

    $buyer = BuyerProfile::factory()->create();
    $admin = User::factory()->admin()->create();
    $partRequest = PartRequest::factory()->for($buyer, 'buyer')->create(['status' => RequestStatus::Paid]);
    $payment = Payment::factory()->create([
        'part_request_id' => $partRequest->id,
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_test_redelivered_notify',
        'status' => PaymentStatus::Pending,
    ]);

    app(ConfirmStripePaymentAction::class)->execute('pi_test_redelivered_notify');

    // Stripe redelivering the same already-confirmed event must hit the
    // "already-confirmed, ignored" early return -- not run
    // SendPaymentConfirmedNotificationsAction a second time. Without this,
    // Stripe's at-least-once webhook delivery would mean a buyer/admin
    // could be notified more than once for the exact same payment.
    app(ConfirmStripePaymentAction::class)->execute('pi_test_redelivered_notify');

    Notification::assertSentToTimes($buyer->user, PaymentConfirmedNotification::class, 1);
    Notification::assertSentToTimes($admin, PaymentConfirmedAdminNotification::class, 1);
});

it('confirms a payment that previously failed -- a retried PaymentIntent can still succeed', function () {
    $payment = Payment::factory()->create([
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_test_789',
        'status' => PaymentStatus::Failed,
    ]);

    app(ConfirmStripePaymentAction::class)->execute('pi_test_789');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Confirmed);
});

it('does nothing, and does not throw, for a PaymentIntent that does not match any payment', function () {
    $attempt = fn () => app(ConfirmStripePaymentAction::class)->execute('pi_unknown_intent');

    expect($attempt)->not->toThrow(Throwable::class);
    expect(Payment::count())->toBe(0);
});

it('only matches payments on the stripe gateway, never another gateway\'s row with a coincidentally equal reference', function () {
    $stubPayment = Payment::factory()->create([
        'gateway' => 'stub',
        'gateway_reference' => 'pi_test_shared',
        'status' => PaymentStatus::Pending,
    ]);

    app(ConfirmStripePaymentAction::class)->execute('pi_test_shared');

    expect($stubPayment->refresh()->status)->toBe(PaymentStatus::Pending);
});
