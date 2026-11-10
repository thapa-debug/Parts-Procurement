<?php

use App\Actions\ResumeStripePaymentAction;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\PaymentFailedException;
use App\Exceptions\PaymentResumeNotAllowedException;
use App\Models\PartRequest;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

uses(RefreshDatabase::class);

/**
 * Swaps Stripe's HTTP transport (the real stripe-php SDK still runs) and
 * records every request, so a test can prove resume only ever RETRIEVES
 * the stored PaymentIntent and never creates a second one. Answers with
 * the queued responses in order, repeating the last one.
 */
class RecordingFakeStripeHttpClientForResume implements ClientInterface
{
    /** @var list<array{method: string, absUrl: string}> */
    public array $requests = [];

    /** @var list<array{0: int, 1: array}> */
    private array $responses;

    /**
     * @param  array{0: int, 1: array}  ...$responses  [http status, body]
     */
    public function __construct(array ...$responses)
    {
        $this->responses = $responses;
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => strtolower($method), 'absUrl' => $absUrl];

        [$code, $body] = count($this->responses) > 1 ? array_shift($this->responses) : $this->responses[0];

        return [json_encode($body), $code, []];
    }

    /** @return list<string> e.g. "get /v1/payment_intents/pi_abandoned" */
    public function calls(): array
    {
        return array_map(fn ($r) => $r['method'].' '.parse_url($r['absUrl'], PHP_URL_PATH), $this->requests);
    }
}

/**
 * An abandoned Stripe checkout: the request awaits payment, its one
 * Payment row points at the PaymentIntent created at "Proceed to payment".
 *
 * @return array{0: PartRequest, 1: Payment}
 */
function abandonedStripeCheckout(PaymentStatus $paymentStatus = PaymentStatus::Pending): array
{
    $request = PartRequest::factory()->create(['status' => RequestStatus::AwaitingPayment, 'buyer_price' => 54_000, 'shipping_fee' => 8_000]);
    $payment = Payment::factory()->create([
        'part_request_id' => $request->id,
        'amount' => 62_000,
        'currency' => 'JPY',
        'gateway' => 'stripe',
        'gateway_reference' => 'pi_abandoned',
        'status' => $paymentStatus,
    ]);

    return [$request, $payment];
}

/**
 * @return array{0: int, 1: array}
 */
function intentResponse(string $id, string $status, int $amount = 62_000, string $currency = 'jpy'): array
{
    return [200, [
        'id' => $id,
        'object' => 'payment_intent',
        'status' => $status,
        'amount' => $amount,
        'currency' => $currency,
        'client_secret' => "{$id}_secret",
    ]];
}

function fakeStripe(array ...$responses): RecordingFakeStripeHttpClientForResume
{
    $client = new RecordingFakeStripeHttpClientForResume(...$responses);
    ApiRequestor::setHttpClient($client);

    return $client;
}

function fakeStripeIntent(string $status, int $amount = 62_000, string $currency = 'jpy'): RecordingFakeStripeHttpClientForResume
{
    return fakeStripe(intentResponse('pi_abandoned', $status, $amount, $currency));
}

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

it('resumes the SAME PaymentIntent -- retrieves it by its stored id and never creates another', function () {
    [$request, $payment] = abandonedStripeCheckout();
    $stripe = fakeStripeIntent('requires_payment_method');

    $clientSecret = app(ResumeStripePaymentAction::class)->execute($request);

    expect($clientSecret)->toBe('pi_abandoned_secret')
        ->and($stripe->requests)->toHaveCount(1)
        ->and($stripe->requests[0]['method'])->toBe('get')
        ->and($stripe->requests[0]['absUrl'])->toEndWith('/v1/payment_intents/pi_abandoned');

    // Nothing changes on our side either: still one payment, still pending,
    // still awaiting payment -- only the webhook ever confirms.
    expect(Payment::where('part_request_id', $request->id)->count())->toBe(1)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
});

it('resumes any intent Stripe still lets the buyer pay', function (string $intentStatus) {
    [$request] = abandonedStripeCheckout();
    fakeStripeIntent($intentStatus);

    expect(app(ResumeStripePaymentAction::class)->execute($request))->toBe('pi_abandoned_secret');
})->with(['requires_payment_method', 'requires_confirmation', 'requires_action']);

it('resumes after a declined attempt -- the same intent takes a different card', function () {
    [$request] = abandonedStripeCheckout(PaymentStatus::Failed);
    $stripe = fakeStripeIntent('requires_payment_method');

    expect(app(ResumeStripePaymentAction::class)->execute($request))->toBe('pi_abandoned_secret')
        ->and(collect($stripe->requests)->where('method', 'post'))->toBeEmpty();
});

it('refuses to resume an intent that is already being paid -- the webhook will confirm it', function (string $intentStatus) {
    [$request] = abandonedStripeCheckout();
    $stripe = fakeStripeIntent($intentStatus);

    expect(fn () => app(ResumeStripePaymentAction::class)->execute($request))
        ->toThrow(fn (PaymentResumeNotAllowedException $e) => expect($e->inProgress)->toBeTrue());
    expect(collect($stripe->requests)->where('method', 'post'))->toBeEmpty();
})->with(['processing', 'succeeded']);

it('replaces a canceled intent with exactly one fresh intent -- nothing left to cancel, old payment kept', function () {
    [$request, $payment] = abandonedStripeCheckout();
    $stripe = fakeStripe(
        intentResponse('pi_abandoned', 'canceled'),
        intentResponse('pi_replacement', 'requires_payment_method'),
    );

    $clientSecret = app(ResumeStripePaymentAction::class)->execute($request);

    expect($clientSecret)->toBe('pi_replacement_secret')
        ->and($stripe->calls())->toBe([
            'get /v1/payment_intents/pi_abandoned',
            'post /v1/payment_intents',
        ]);

    // The old row is kept (never destroyed), marked failed; one new pending
    // row carries the replacement for the same snapshotted total.
    $payments = Payment::where('part_request_id', $request->id)->orderBy('id')->get();
    expect($payments)->toHaveCount(2)
        ->and($payments[0]->id)->toBe($payment->id)
        ->and($payments[0]->status)->toBe(PaymentStatus::Failed)
        ->and($payments[0]->gateway_reference)->toBe('pi_abandoned')
        ->and($payments[1]->status)->toBe(PaymentStatus::Pending)
        ->and($payments[1]->gateway)->toBe('stripe')
        ->and($payments[1]->gateway_reference)->toBe('pi_replacement')
        ->and($payments[1]->amount)->toBe(62_000)
        ->and($request->fresh()->status)->toBe(RequestStatus::AwaitingPayment);
});

it('cancels a still-live unusable intent at Stripe BEFORE creating its replacement', function () {
    [$request] = abandonedStripeCheckout();
    // Any status Stripe won't take a card on that isn't already canceled.
    $stripe = fakeStripe(
        intentResponse('pi_abandoned', 'requires_reauthorization'),
        intentResponse('pi_abandoned', 'canceled'),
        intentResponse('pi_replacement', 'requires_payment_method'),
    );

    expect(app(ResumeStripePaymentAction::class)->execute($request))->toBe('pi_replacement_secret')
        ->and($stripe->calls())->toBe([
            'get /v1/payment_intents/pi_abandoned',
            'post /v1/payment_intents/pi_abandoned/cancel',
            'post /v1/payment_intents',
        ]);
});

it('never creates a replacement when the old intent cannot be canceled', function () {
    [$request, $payment] = abandonedStripeCheckout();
    $stripe = fakeStripe(
        intentResponse('pi_abandoned', 'requires_reauthorization'),
        [400, ['error' => ['type' => 'invalid_request_error', 'code' => 'payment_intent_unexpected_state', 'message' => 'cannot cancel']]],
    );

    expect(fn () => app(ResumeStripePaymentAction::class)->execute($request))
        ->toThrow(fn (PaymentResumeNotAllowedException $e) => expect($e->inProgress)->toBeFalse());
    expect($stripe->calls())->toBe([
        'get /v1/payment_intents/pi_abandoned',
        'post /v1/payment_intents/pi_abandoned/cancel',
    ])
        ->and(Payment::where('part_request_id', $request->id)->count())->toBe(1)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('never creates a replacement when Stripe reports the old intent still not canceled', function () {
    [$request] = abandonedStripeCheckout();
    $stripe = fakeStripe(
        intentResponse('pi_abandoned', 'requires_reauthorization'),
        intentResponse('pi_abandoned', 'requires_reauthorization'),
    );

    expect(fn () => app(ResumeStripePaymentAction::class)->execute($request))
        ->toThrow(PaymentResumeNotAllowedException::class);
    expect($stripe->calls())->not->toContain('post /v1/payment_intents')
        ->and(Payment::where('part_request_id', $request->id)->count())->toBe(1);
});

it('rolls back cleanly when the replacement intent cannot be created -- old payment untouched', function () {
    [$request, $payment] = abandonedStripeCheckout();
    fakeStripe(
        intentResponse('pi_abandoned', 'canceled'),
        [400, ['error' => ['type' => 'invalid_request_error', 'message' => 'boom']]],
    );

    expect(fn () => app(ResumeStripePaymentAction::class)->execute($request))
        ->toThrow(PaymentFailedException::class);
    expect(Payment::where('part_request_id', $request->id)->count())->toBe(1)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('resuming again after a replacement reuses the replacement -- never a third intent', function () {
    [$request] = abandonedStripeCheckout();
    fakeStripe(
        intentResponse('pi_abandoned', 'canceled'),
        intentResponse('pi_replacement', 'requires_payment_method'),
    );
    app(ResumeStripePaymentAction::class)->execute($request);

    $stripe = fakeStripe(intentResponse('pi_replacement', 'requires_payment_method'));

    expect(app(ResumeStripePaymentAction::class)->execute($request->fresh()))->toBe('pi_replacement_secret')
        ->and($stripe->calls())->toBe(['get /v1/payment_intents/pi_replacement'])
        ->and(Payment::where('part_request_id', $request->id)->count())->toBe(2);
});

it('refuses an intent whose amount or currency no longer matches our payment record', function (int $amount, string $currency) {
    [$request] = abandonedStripeCheckout();
    fakeStripeIntent('requires_payment_method', $amount, $currency);

    expect(fn () => app(ResumeStripePaymentAction::class)->execute($request))
        ->toThrow(PaymentResumeNotAllowedException::class);
})->with([
    'different amount' => [61_000, 'jpy'],
    'different currency' => [62_000, 'usd'],
]);

it('never calls Stripe for a request that is not awaiting payment', function (RequestStatus $status) {
    [$request] = abandonedStripeCheckout();
    $request->update(['status' => $status]);
    $stripe = fakeStripeIntent('requires_payment_method');

    expect(fn () => app(ResumeStripePaymentAction::class)->execute($request->fresh()))
        ->toThrow(PaymentResumeNotAllowedException::class);
    expect($stripe->requests)->toBeEmpty();
})->with([
    'quoted' => RequestStatus::Quoted,
    'paid' => RequestStatus::Paid,
]);

it('never calls Stripe when there is no resumable stripe payment for the request', function () {
    [$request, $payment] = abandonedStripeCheckout();
    $payment->update(['gateway' => 'stub']);
    $stripe = fakeStripeIntent('requires_payment_method');

    expect(fn () => app(ResumeStripePaymentAction::class)->execute($request))
        ->toThrow(PaymentResumeNotAllowedException::class);
    expect($stripe->requests)->toBeEmpty();
});
