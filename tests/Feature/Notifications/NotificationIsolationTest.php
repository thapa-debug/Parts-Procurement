<?php

use App\Actions\ApproveBuyerAction;
use App\Actions\BroadcastRequestAction;
use App\Actions\PresentQuoteAction;
use App\Actions\RegisterBuyerAction;
use App\Actions\SelectQuoteAction;
use App\Actions\SubmitPartRequestAction;
use App\Actions\SubmitVendorResponseAction;
use App\Enums\PartType;
use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\Maker;
use App\Models\PartRequest;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use App\Notifications\BuyerApprovedNotification;
use App\Notifications\BuyerRegisteredNotification;
use App\Notifications\PartRequestSubmittedNotification;
use App\Notifications\QuotePresentedNotification;
use App\Notifications\QuoteSelectedNotification;
use App\Notifications\RequestBroadcastNotification;
use App\Notifications\VendorResponseSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// CLAUDE.md §4 isolation, applied to Phase 3 Slice 1's database-channel
// notifications: a vendor's notification must never reveal the buyer's
// identity or another vendor's data; a buyer's notification must never
// reveal vendor cost or identity. Every assertion below checks both that
// the RIGHT party got notified and that the notification's own stored
// data (toArray()) never carries the forbidden fields -- not just "no
// secret string happens to appear today", checked via json_encode() the
// same way the buyer Livewire wire-snapshot isolation tests do.

it('tells only the invited vendor about a broadcast, never the buyer\'s identity or another vendor', function () {
    Notification::fake();

    $buyer = BuyerProfile::factory()->create(['company_name' => 'Secret Buyer Co']);
    $request = PartRequest::factory()->for($buyer, 'buyer')->create(['status' => RequestStatus::New, 'part_name' => 'Front bumper']);
    $invited = VendorProfile::factory()->create();
    $notInvited = VendorProfile::factory()->create(['company_name' => 'Uninvited Vendor Co']);

    app(BroadcastRequestAction::class)->execute($request, [$invited->id]);

    Notification::assertSentTo($invited->user, RequestBroadcastNotification::class, function ($notification, $channels) use ($invited, $request) {
        expect($channels)->toBe(['database']);

        $data = $notification->toArray($invited->user);

        expect($data['request_id'])->toBe($request->id)
            ->and($data['request_code'])->toBe($request->request_code)
            ->and($data['part_name'])->toBe('Front bumper')
            ->and($data['url'])->toBe(route('vendor.inbox.show', $request));

        $encoded = json_encode($data);
        expect($encoded)->not->toContain('Secret Buyer Co')
            ->and($encoded)->not->toContain('Uninvited Vendor Co');

        return true;
    });

    Notification::assertNotSentTo($notInvited->user, RequestBroadcastNotification::class);
});

it('tells every admin, and only admins, when a vendor submits a quote', function () {
    Notification::fake();

    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();
    $buyer = BuyerProfile::factory()->create();
    $request = PartRequest::factory()->for($buyer, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create(['company_name' => 'Yamato Auto']);
    $request->vendors()->attach($vendor->id, ['invited_at' => now()]);

    $response = app(SubmitVendorResponseAction::class)->execute($request, $vendor, ['cost_price' => 45_000]);

    foreach ([$adminA, $adminB] as $admin) {
        Notification::assertSentTo($admin, VendorResponseSubmittedNotification::class, function ($notification, $channels) use ($admin, $request) {
            expect($channels)->toBe(['database']);

            $data = $notification->toArray($admin);

            expect($data['request_id'])->toBe($request->id)
                ->and($data['vendor_company_name'])->toBe('Yamato Auto')
                ->and($data['url'])->toBe(route('admin.requests.show', $request));

            return true;
        });
    }

    Notification::assertNotSentTo($buyer->user, VendorResponseSubmittedNotification::class);
    Notification::assertNotSentTo($vendor->user, VendorResponseSubmittedNotification::class);
});

it('tells only the buyer about a presented quote, never the vendor\'s identity or cost', function () {
    Notification::fake();

    $buyer = BuyerProfile::factory()->create();
    $request = PartRequest::factory()->for($buyer, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create(['company_name' => 'Secret Vendor Co', 'contact_person' => 'Secret Contact Person']);
    $response = VendorResponse::factory()->create([
        'part_request_id' => $request->id,
        'vendor_id' => $vendor->id,
        'cost_price' => 45_000,
    ]);

    app(PresentQuoteAction::class)->execute($request, $response);

    Notification::assertSentTo($buyer->user, QuotePresentedNotification::class, function ($notification, $channels) use ($buyer, $request) {
        expect($channels)->toBe(['database']);

        $data = $notification->toArray($buyer->user);

        expect($data['request_id'])->toBe($request->id)
            ->and($data['buyer_price'])->toBe(54_000) // max(45000 * 20%, 2000) = 9000 -> 54,000
            ->and($data['url'])->toBe(route('buyer.requests.show', $request))
            ->and($data)->not->toHaveKey('cost_price')
            ->and($data)->not->toHaveKey('vendor_id')
            ->and($data)->not->toHaveKey('vendor_response_id')
            ->and($data)->not->toHaveKey('vendor_company_name');

        $encoded = json_encode($data);
        expect($encoded)->not->toContain('Secret Vendor Co')
            ->and($encoded)->not->toContain('Secret Contact Person')
            ->and($encoded)->not->toContain('45000')
            ->and($encoded)->not->toContain('45,000');

        return true;
    });

    Notification::assertNotSentTo($vendor->user, QuotePresentedNotification::class);
});

it('tells every admin, and only admins, when a buyer selects a quote', function () {
    Notification::fake();

    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();
    $buyer = BuyerProfile::factory()->create(['company_name' => 'Acme Imports']);
    $request = PartRequest::factory()->for($buyer, 'buyer')->create(['status' => RequestStatus::VendorInquiry]);
    $vendor = VendorProfile::factory()->create();
    $response = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendor->id, 'cost_price' => 45_000]);
    $presentedQuote = app(PresentQuoteAction::class)->execute($request, $response);

    app(SelectQuoteAction::class)->execute($request->fresh(), $presentedQuote);

    foreach ([$adminA, $adminB] as $admin) {
        Notification::assertSentTo($admin, QuoteSelectedNotification::class, function ($notification, $channels) use ($admin, $request) {
            expect($channels)->toBe(['database']);

            $data = $notification->toArray($admin);

            expect($data['request_id'])->toBe($request->id)
                ->and($data['buyer_company_name'])->toBe('Acme Imports')
                ->and($data['buyer_price'])->toBe(54_000)
                ->and($data['url'])->toBe(route('admin.requests.show', $request));

            return true;
        });
    }

    Notification::assertNotSentTo($buyer->user, QuoteSelectedNotification::class);
    Notification::assertNotSentTo($vendor->user, QuoteSelectedNotification::class);
});

it('tells every admin, and only admins, when a buyer registers', function () {
    Notification::fake();

    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();
    $country = Country::factory()->create();

    $result = app(RegisterBuyerAction::class)->execute(
        name: 'Jane Buyer',
        email: 'jane@example.com',
        password: 'password',
        companyName: 'New Buyer Co',
        countryId: $country->id,
        phone: '555-0100',
    );

    foreach ([$adminA, $adminB] as $admin) {
        Notification::assertSentTo($admin, BuyerRegisteredNotification::class, function ($notification, $channels) use ($admin, $result) {
            expect($channels)->toBe(['database']);

            $data = $notification->toArray($admin);

            expect($data['buyer_profile_id'])->toBe($result['buyer_profile']->id)
                ->and($data['buyer_company_name'])->toBe('New Buyer Co')
                ->and($data['url'])->toBe(route('admin.buyers.show', $result['buyer_profile']));

            return true;
        });
    }

    Notification::assertNotSentTo($result['user'], BuyerRegisteredNotification::class);
});

it('tells only the buyer when they are approved, with no cross-party data at all', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $buyer = BuyerProfile::factory()->pending()->create();

    app(ApproveBuyerAction::class)->execute($buyer, $admin);

    Notification::assertSentTo($buyer->user, BuyerApprovedNotification::class, function ($notification, $channels) use ($buyer) {
        expect($channels)->toBe(['database']);

        $data = $notification->toArray($buyer->user);

        expect($data)->toBe([
            'type' => 'buyer_approved',
            'url' => route('buyer.requests.index'),
        ]);

        return true;
    });

    Notification::assertNotSentTo($admin, BuyerApprovedNotification::class);
});

it('tells every admin, and only admins, when a buyer submits a new request', function () {
    Notification::fake();

    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();
    $buyer = BuyerProfile::factory()->create(['company_name' => 'Global Parts Ltd']);
    $maker = Maker::factory()->create();

    $request = app(SubmitPartRequestAction::class)->execute(
        buyer: $buyer,
        partType: PartType::Used,
        makerId: $maker->id,
        carModel: 'Model-123',
        vin: 'JT1234567890',
        oemPartNumber: null,
        partName: 'Alternator',
        referenceUrl: null,
        memo: null,
    );

    foreach ([$adminA, $adminB] as $admin) {
        Notification::assertSentTo($admin, PartRequestSubmittedNotification::class, function ($notification, $channels) use ($admin, $request) {
            expect($channels)->toBe(['database']);

            $data = $notification->toArray($admin);

            expect($data['request_id'])->toBe($request->id)
                ->and($data['buyer_company_name'])->toBe('Global Parts Ltd')
                ->and($data['part_name'])->toBe('Alternator')
                ->and($data['url'])->toBe(route('admin.requests.show', $request));

            return true;
        });
    }

    Notification::assertNotSentTo($buyer->user, PartRequestSubmittedNotification::class);
});
