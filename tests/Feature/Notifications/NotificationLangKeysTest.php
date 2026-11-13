<?php

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmedAdminNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\UrlQuotePricedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;

uses(RefreshDatabase::class);

// Guards against a notification referencing a lang key that doesn't exist:
// Laravel then prints the key itself ("notifications.mail.x.line") instead
// of text. Renders every payment/quote-ready variant end to end and fails
// on any raw key or unsubstituted :placeholder.

function paymentForVariant(array $requestAttributes): Payment
{
    $request = PartRequest::factory()
        ->for(BuyerProfile::factory()->state(['company_name' => 'Acme Imports']), 'buyer')
        ->create(['status' => RequestStatus::Paid, 'buyer_price' => 54_000, ...$requestAttributes]);

    return Payment::factory()->create([
        'part_request_id' => $request->id,
        'amount' => $request->is_free ? 0 : 62_000,
        'status' => PaymentStatus::Confirmed,
        'paid_at' => now(),
    ]);
}

/**
 * The email's text plus the bell line, rendered with its params the way
 * NotificationCenter::notificationText() does (buyer_price formatted).
 *
 * @param  array<string, mixed>  $mail
 * @param  array<string, mixed>  $data
 */
function expectFullyTranslated(array $mail, array $data): void
{
    expect(Lang::has('notifications.types.'.$data['type'], 'en'))->toBeTrue();

    if (isset($data['buyer_price'])) {
        $data['buyer_price'] = number_format($data['buyer_price']);
    }

    $text = implode(' ', [
        $mail['subject'], ...$mail['introLines'], ...$mail['outroLines'], $mail['actionText'],
        __('notifications.types.'.$data['type'], $data),
    ]);

    expect($text)->not->toContain('notifications.')
        ->and($text)->not->toMatch('/(?<!\w):[a-z_]+/');
}

it('renders every payment-confirmed variant with real text', function (string $notificationClass, string $recipient, array $requestAttributes, string $expectedType) {
    $user = $recipient === 'admin' ? User::factory()->admin()->create() : User::factory()->buyer()->create();
    $notification = new $notificationClass(paymentForVariant($requestAttributes));

    $data = $notification->toArray($user);

    expect($data['type'])->toBe($expectedType);

    expectFullyTranslated($notification->toMail($user)->toArray(), $data);
})->with([
    'buyer, paid' => [PaymentConfirmedNotification::class, 'buyer', [], 'payment_confirmed'],
    'buyer, free' => [PaymentConfirmedNotification::class, 'buyer', ['is_free' => true], 'payment_confirmed_free'],
    'admin, paid' => [PaymentConfirmedAdminNotification::class, 'admin', [], 'payment_confirmed_admin'],
    'admin, free' => [PaymentConfirmedAdminNotification::class, 'admin', ['is_free' => true], 'payment_confirmed_admin_free'],
    'admin, URL-sourced' => [PaymentConfirmedAdminNotification::class, 'admin', ['is_url_sourced' => true], 'payment_confirmed_admin_url'],
]);

it('renders the URL quote-ready email with real text', function () {
    $request = PartRequest::factory()->for(BuyerProfile::factory(), 'buyer')->create([
        'status' => RequestStatus::Quoted, 'is_url_sourced' => true, 'buyer_price' => 54_000,
    ]);
    $notification = new UrlQuotePricedNotification($request);

    expectFullyTranslated(
        $notification->toMail($request->buyer->user)->toArray(),
        $notification->toArray($request->buyer->user),
    );
});

it('has a bell line for every email type and vice versa', function () {
    $types = array_keys(Lang::get('notifications.types', [], 'en'));
    $mails = array_diff(array_keys(Lang::get('notifications.mail', [], 'en')), ['no_reply_notice']);

    expect(array_values(array_diff($types, $mails)))->toBe([])
        ->and(array_values(array_diff($mails, $types)))->toBe([]);
});
