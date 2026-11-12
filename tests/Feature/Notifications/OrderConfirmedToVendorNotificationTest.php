<?php

use App\Actions\ConfirmOrderToVendorAction;
use App\Enums\DeliveryMethod;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Livewire\NotificationCenter;
use App\Models\BuyerProfile;
use App\Models\CompanyAddress;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use App\Notifications\OrderConfirmedToVendorNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// The Japanese-email test does a real send and reads the 'array' mailer.
// Pinned here rather than trusted from phpunit.xml: CI's test job sets its
// own MAIL_MAILER (log) and QUEUE_CONNECTION (redis), which override
// phpunit.xml -- the (ShouldQueue) notification would be queued, never
// reaching the array mailer.
beforeEach(function () {
    config(['mail.default' => 'array', 'queue.default' => 'sync']);
});

// 発注確定: the confirmed vendor learns they were chosen, and where to
// deliver. Same isolation discipline as the vendor's own confirmed-order
// view (Vendor\RequestResponse) -- the company receiving address only,
// never the buyer.

/**
 * A `paid` request with a confirmed payment, two invited vendors who both
 * responded, the first one's response selected. The buyer has a
 * distinctive company name and shipping address that must never reach a
 * vendor.
 *
 * @return array{request: PartRequest, confirmed: VendorProfile, other: VendorProfile, address: CompanyAddress}
 */
function orderReadyToConfirm(DeliveryMethod $deliveryMethod = DeliveryMethod::Deliver): array
{
    $buyer = BuyerProfile::factory()->create(['company_name' => 'Secret Buyer Co']);
    $request = PartRequest::factory()->for($buyer, 'buyer')->create([
        'status' => RequestStatus::Paid,
        'part_name' => 'Front bumper',
        'shipping_recipient_name' => 'Buyer Recipient Person',
        'shipping_address_line1' => '42 Buyer Street',
    ]);

    $confirmed = VendorProfile::factory()->create(['delivery_method' => $deliveryMethod]);
    $other = VendorProfile::factory()->create();

    foreach ([$confirmed, $other] as $vendor) {
        $request->vendors()->attach($vendor->id, ['invited_at' => now()]);
    }

    $response = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $confirmed->id]);
    VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $other->id]);
    $request->update(['selected_response_id' => $response->id]);
    Payment::factory()->create(['part_request_id' => $request->id, 'status' => PaymentStatus::Confirmed]);

    $address = CompanyAddress::factory()->create([
        'label' => 'Chiba yard',
        'recipient_name' => 'Receiving desk',
        'phone' => '043-123-4567',
        'postal_code' => '260-0001',
        'prefecture' => 'Chiba',
        'city' => 'Chiba-shi',
        'address_line1' => '1-2-3 Chuo',
        'address_line2' => 'Gate B',
    ]);

    return ['request' => $request->fresh(), 'confirmed' => $confirmed, 'other' => $other, 'address' => $address];
}

/**
 * Every string the notification must never carry.
 *
 * @return array<int, string>
 */
function buyerOnlyStrings(): array
{
    return ['Secret Buyer Co', 'Buyer Recipient Person', '42 Buyer Street'];
}

// --- firing from ConfirmOrderToVendorAction -----------------------------------

it('notifies the confirmed vendor exactly once, on both the in-app and mail channels', function () {
    Notification::fake();
    ['request' => $request, 'confirmed' => $confirmed, 'address' => $address] = orderReadyToConfirm();

    app(ConfirmOrderToVendorAction::class)->execute($request, $address);

    Notification::assertSentToTimes($confirmed->user, OrderConfirmedToVendorNotification::class, 1);
    Notification::assertSentTo($confirmed->user, OrderConfirmedToVendorNotification::class, function ($notification, $channels) {
        return $channels === ['database', 'mail'];
    });
});

it('notifies nobody else -- not the other invited vendor, the buyer, or the admins', function () {
    Notification::fake();
    ['request' => $request, 'other' => $other, 'address' => $address] = orderReadyToConfirm();
    $admin = User::factory()->admin()->create();

    app(ConfirmOrderToVendorAction::class)->execute($request, $address);

    Notification::assertNotSentTo($other->user, OrderConfirmedToVendorNotification::class);
    Notification::assertNotSentTo($request->buyer->user, OrderConfirmedToVendorNotification::class);
    Notification::assertNotSentTo($admin, OrderConfirmedToVendorNotification::class);
    Notification::assertCount(1);
});

it('sends nothing when the payment gate refuses the confirmation', function () {
    Notification::fake();
    ['request' => $request, 'address' => $address] = orderReadyToConfirm();
    $request->payments()->update(['status' => PaymentStatus::Pending]);

    expect(fn () => app(ConfirmOrderToVendorAction::class)->execute($request, $address))->toThrow(Exception::class);

    Notification::assertNothingSent();
});

it('still confirms the order when the notification fails to send -- best-effort (CLAUDE.md §10)', function () {
    ['request' => $request, 'address' => $address] = orderReadyToConfirm();

    DatabaseNotification::creating(function () {
        throw new RuntimeException('forced failure for test');
    });

    $result = app(ConfirmOrderToVendorAction::class)->execute($request, $address);

    expect($result->status)->toBe(RequestStatus::OrderedToVendor)
        ->and($result->receiving_label)->toBe('Chiba yard');
});

// --- content + isolation ------------------------------------------------------

it('stores only the request and the receiving label in-app, never anything about the buyer', function () {
    ['request' => $request, 'confirmed' => $confirmed, 'address' => $address] = orderReadyToConfirm();
    $request = app(ConfirmOrderToVendorAction::class)->execute($request, $address);

    $data = (new OrderConfirmedToVendorNotification($request))->toArray($confirmed->user);

    expect($data)->toBe([
        'type' => 'order_confirmed_to_vendor',
        'request_id' => $request->id,
        'request_code' => $request->request_code,
        'part_name' => 'Front bumper',
        'receiving_label' => 'Chiba yard',
        'url' => route('vendor.inbox.show', $request),
    ]);

    foreach (buyerOnlyStrings() as $forbidden) {
        expect(json_encode($data))->not->toContain($forbidden);
    }
});

it('mails the request, the full delivery address, and a link to the order -- never anything about the buyer', function () {
    ['request' => $request, 'confirmed' => $confirmed, 'address' => $address] = orderReadyToConfirm();
    $request = app(ConfirmOrderToVendorAction::class)->execute($request, $address);

    $mail = (new OrderConfirmedToVendorNotification($request))->toMail($confirmed->user);
    $text = json_encode([$mail->subject, $mail->introLines, $mail->outroLines, $mail->actionText], JSON_UNESCAPED_UNICODE);

    expect($mail->subject)->toContain($request->request_code)
        ->and($mail->actionUrl)->toBe(route('vendor.inbox.show', $request))
        ->and($text)->toContain('Chiba yard')
        ->and($text)->toContain('260-0001')
        ->and($text)->toContain('Chiba-shi')
        ->and($text)->toContain('1-2-3 Chuo')
        ->and($text)->toContain('Gate B')
        ->and($text)->toContain('Receiving desk')
        ->and($text)->toContain('043-123-4567');

    foreach (buyerOnlyStrings() as $forbidden) {
        expect($text)->not->toContain($forbidden);
    }
});

it('closes with the order-specific line, not the inquiry-style "please respond" notice', function () {
    ['request' => $request, 'confirmed' => $confirmed, 'address' => $address] = orderReadyToConfirm();
    $request = app(ConfirmOrderToVendorAction::class)->execute($request, $address);

    $mail = (new OrderConfirmedToVendorNotification($request))->toMail($confirmed->user);

    expect($mail->outroLines)->toBe([__('notifications.mail.order_confirmed_to_vendor.closing')])
        ->and(__('notifications.mail.order_confirmed_to_vendor.closing', [], 'ja'))->toContain('ご不明点はポータルよりお問い合わせください');
});

it('tells the vendor how to get the part there, from their own delivery method', function (DeliveryMethod $method) {
    ['request' => $request, 'confirmed' => $confirmed, 'address' => $address] = orderReadyToConfirm($method);
    $request = app(ConfirmOrderToVendorAction::class)->execute($request, $address);

    $mail = (new OrderConfirmedToVendorNotification($request))->toMail($confirmed->user);

    expect($mail->introLines)->toContain(__('notifications.mail.order_confirmed_to_vendor.instruction.'.$method->value));
})->with([DeliveryMethod::Carry, DeliveryMethod::Deliver, DeliveryMethod::Both]);

it('renders the vendor email in Japanese', function () {
    ['request' => $request, 'confirmed' => $confirmed, 'address' => $address] = orderReadyToConfirm(DeliveryMethod::Carry);
    $request = app(ConfirmOrderToVendorAction::class)->execute($request, $address);

    // A real send through the 'array' mailer (phpunit.xml), so Laravel's
    // own HasLocalePreference switch is what's under test.
    $confirmed->user->notify(new OrderConfirmedToVendorNotification($request));

    /** @var SentMessage $sent */
    $sent = Mail::mailer('array')->getSymfonyTransport()->messages()->last();
    $email = $sent->getOriginalMessage();

    expect($email->getSubject())->toBe(__('notifications.mail.order_confirmed_to_vendor.subject', ['part_name' => 'Front bumper', 'request_code' => $request->request_code], 'ja'))
        ->and($email->getHtmlBody())->toContain(e(__('notifications.mail.order_confirmed_to_vendor.address_heading', ['label' => 'Chiba yard'], 'ja')))
        ->and($email->getHtmlBody())->toContain(e(__('notifications.mail.order_confirmed_to_vendor.instruction.carry', [], 'ja')))
        ->and($email->getHtmlBody())->not->toContain('Delivery address');

    foreach (buyerOnlyStrings() as $forbidden) {
        expect($email->getHtmlBody())->not->toContain($forbidden);
    }
});

it('shows an English line in the vendor\'s in-app notification centre', function () {
    ['request' => $request, 'confirmed' => $confirmed, 'address' => $address] = orderReadyToConfirm();
    $request = app(ConfirmOrderToVendorAction::class)->execute($request, $address);

    $confirmed->user->notify(new OrderConfirmedToVendorNotification($request));
    $stored = $confirmed->user->notifications()->where('data->type', 'order_confirmed_to_vendor')->latest()->firstOrFail();

    expect(app(NotificationCenter::class)->notificationText($stored))
        ->toBe("Order confirmed: Front bumper ({$request->request_code}) -- please deliver to Chiba yard.");
});
