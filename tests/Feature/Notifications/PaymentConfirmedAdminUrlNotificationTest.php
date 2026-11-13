<?php

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Livewire\NotificationCenter;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentConfirmedAdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// A URL-sourced order has no vendor -- the admin buys from the reference
// URL -- so its "payment confirmed" prompt must not say "confirm the order
// to the vendor".

function confirmedPaymentFor(bool $urlSourced): Payment
{
    $request = PartRequest::factory()
        ->for(BuyerProfile::factory()->state(['company_name' => 'Acme Imports']), 'buyer')
        ->create([
            'status' => RequestStatus::Paid,
            'is_url_sourced' => $urlSourced,
            'buyer_price' => 54_000,
        ]);

    return Payment::factory()->create([
        'part_request_id' => $request->id,
        'amount' => 62_000,
        'status' => PaymentStatus::Confirmed,
        'paid_at' => now(),
    ]);
}

it('tells the admin to purchase from the reference URL for a URL-sourced order', function () {
    $admin = User::factory()->admin()->create();
    $payment = confirmedPaymentFor(urlSourced: true);
    $notification = new PaymentConfirmedAdminNotification($payment);

    $mail = $notification->toMail($admin)->toArray();
    $params = [
        'buyer_company_name' => 'Acme Imports',
        'request_code' => $payment->partRequest->request_code,
        'amount' => '62,000',
    ];

    expect($notification->toArray($admin)['type'])->toBe('payment_confirmed_admin_url')
        ->and($mail['introLines'])->toContain(__('notifications.mail.payment_confirmed_admin_url.line', $params))
        ->and(strtolower(json_encode($mail)))->not->toContain('vendor');
});

it('renders the URL wording in the admin notification centre', function () {
    $admin = User::factory()->admin()->create();
    $payment = confirmedPaymentFor(urlSourced: true);

    $admin->notify(new PaymentConfirmedAdminNotification($payment));

    Livewire::actingAs($admin)
        ->test(NotificationCenter::class)
        ->assertSee(__('notifications.types.payment_confirmed_admin_url', [
            'buyer_company_name' => 'Acme Imports',
            'request_code' => $payment->partRequest->request_code,
        ]))
        ->assertDontSee('to the vendor');
});

it('keeps the confirm-to-vendor wording for a vendor-flow order', function () {
    $admin = User::factory()->admin()->create();
    $payment = confirmedPaymentFor(urlSourced: false);
    $notification = new PaymentConfirmedAdminNotification($payment);

    expect($notification->toArray($admin)['type'])->toBe('payment_confirmed_admin')
        ->and($notification->toMail($admin)->subject)
        ->toBe(__('notifications.mail.payment_confirmed_admin.subject', [
            'buyer_company_name' => 'Acme Imports',
            'request_code' => $payment->partRequest->request_code,
        ]));
});
