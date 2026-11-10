<?php

use App\Enums\RequestStatus;
use App\Livewire\Admin\RequestBoard;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- authorization -----------------------------------------------------

it('does not let a buyer or vendor mount the request board', function () {
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();

    Livewire::actingAs($buyer)->test(RequestBoard::class)->assertForbidden();
    Livewire::actingAs($vendor)->test(RequestBoard::class)->assertForbidden();
});

it('lets an admin mount the request board', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(RequestBoard::class)
        ->assertSee(__('admin.request_board.title'));
});

// --- empty state ---------------------------------------------------------

it('shows a helpful empty state when there are no requests at all', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(RequestBoard::class)
        ->assertSee(__('admin.request_board.empty_all'));
});

// --- listing / search --------------------------------------------------

it('lists requests and filters them by search', function () {
    $admin = User::factory()->admin()->create();
    $match = PartRequest::factory()->create(['car_model' => 'Crown Athlete']);
    $other = PartRequest::factory()->create(['car_model' => 'Skyline GT-R']);

    Livewire::actingAs($admin)
        ->test(RequestBoard::class)
        ->assertSee('Crown Athlete')
        ->assertSee('Skyline GT-R')
        ->set('search', 'Crown')
        ->assertSee('Crown Athlete')
        ->assertDontSee('Skyline GT-R');

    expect($match->car_model)->toBe('Crown Athlete')
        ->and($other->car_model)->toBe('Skyline GT-R');
});

it('finds a request by its request code', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create(['request_code' => 'REQ-000042']);

    Livewire::actingAs($admin)
        ->test(RequestBoard::class)
        ->set('search', 'REQ-000042')
        ->assertSee($request->car_model);
});

it('finds a request by the buyer\'s company name', function () {
    $admin = User::factory()->admin()->create();
    $buyer = BuyerProfile::factory()->create(['company_name' => 'Acme Imports']);
    $request = PartRequest::factory()->for($buyer, 'buyer')->create();

    Livewire::actingAs($admin)
        ->test(RequestBoard::class)
        ->set('search', 'Acme Imports')
        ->assertSee($request->request_code);
});

// --- 無償 (free) flow (CLAUDE.md §14 Phase 4 slice 5) ----------------------

it('shows a Free (無償) badge for a free request, but not for a regular paid one', function () {
    $admin = User::factory()->admin()->create();
    $freeRequest = PartRequest::factory()->create(['is_free' => true, 'car_model' => 'Free Model']);
    $paidRequest = PartRequest::factory()->create(['is_free' => false, 'car_model' => 'Paid Model']);

    $html = Livewire::actingAs($admin)->test(RequestBoard::class)->html();

    expect(substr_count($html, __('admin.request_board.free_badge')))->toBe(1);

    Livewire::actingAs($admin)
        ->test(RequestBoard::class)
        ->set('search', 'Free Model')
        ->assertSee(__('admin.request_board.free_badge'));

    Livewire::actingAs($admin)
        ->test(RequestBoard::class)
        ->set('search', 'Paid Model')
        ->assertDontSee(__('admin.request_board.free_badge'));
});

// --- quotes received (vendor_inquiry) -------------------------------------------

it('shows how many invited vendors have quoted on a vendor_inquiry row, noting no-stock replies separately', function () {
    $admin = User::factory()->admin()->create();
    // 5 invited: 3 quoted, 1 no stock, 1 not replied yet.
    inquiryWithResponses(invited: 5, quotes: 3, noStock: 1);

    Livewire::actingAs($admin)
        ->test(RequestBoard::class)
        ->assertSee('3 / 5 quotes received')
        ->assertSee('1 no stock');
});

it('shows 0 of N, with no no-stock note, while nobody has replied', function () {
    $admin = User::factory()->admin()->create();
    inquiryWithResponses(invited: 2, quotes: 0, noStock: 0);

    Livewire::actingAs($admin)
        ->test(RequestBoard::class)
        ->assertSee('0 / 2 quotes received')
        ->assertDontSee(__('admin.request_board.no_stock_replies', ['count' => 0]));
});

it('never counts a no-stock reply as a quote', function () {
    $admin = User::factory()->admin()->create();
    inquiryWithResponses(invited: 3, quotes: 1, noStock: 2);

    Livewire::actingAs($admin)
        ->test(RequestBoard::class)
        ->assertSee('1 / 3 quote received')
        ->assertSee('2 no stock');
});

it('shows the quote count only at the vendor_inquiry stage', function () {
    $admin = User::factory()->admin()->create();
    inquiryWithResponses(invited: 2, quotes: 2, noStock: 0, attributes: ['status' => RequestStatus::Quoted]);

    Livewire::actingAs($admin)
        ->test(RequestBoard::class)
        ->assertDontSee('quotes received');
});

it('counts every row in the page query -- no extra query per inquiry row', function () {
    $admin = User::factory()->admin()->create();
    inquiryWithResponses(invited: 2, quotes: 1, noStock: 1);

    $queriesFor = function () use ($admin) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::actingAs($admin)->test(RequestBoard::class);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $queriesFor(); // warm-up: the first render also does one-off lookups
    $withOne = $queriesFor();
    inquiryWithResponses(invited: 3, quotes: 2, noStock: 0);
    inquiryWithResponses(invited: 4, quotes: 0, noStock: 2);

    expect($queriesFor())->toBe($withOne);
});

// --- status tabs -----------------------------------------------------------

it('filters requests into the correct tab by status, with accurate counts', function () {
    $admin = User::factory()->admin()->create();

    $new = PartRequest::factory()->create(['status' => RequestStatus::New, 'car_model' => 'New Model']);
    $inquiring = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry, 'car_model' => 'Inquiring Model']);
    $quoted = PartRequest::factory()->create(['status' => RequestStatus::Quoted, 'car_model' => 'Quoted Model']);
    $paid = PartRequest::factory()->create(['status' => RequestStatus::Paid, 'car_model' => 'Paid Model']);
    $orderedToVendor = PartRequest::factory()->create(['status' => RequestStatus::OrderedToVendor, 'car_model' => 'Ordered Model']);
    $procurementFailed = PartRequest::factory()->create(['status' => RequestStatus::ProcurementFailed, 'car_model' => 'Failed Model']);
    $shipped = PartRequest::factory()->create(['status' => RequestStatus::Shipped, 'car_model' => 'Shipped Model']);
    $received = PartRequest::factory()->create(['status' => RequestStatus::Received, 'car_model' => 'Received Model']);

    $component = Livewire::actingAs($admin)->test(RequestBoard::class);

    $component->set('tab', 'new')
        ->assertSee('New Model')
        ->assertDontSee('Inquiring Model')
        ->assertDontSee('Quoted Model')
        ->assertDontSee('Paid Model')
        ->assertDontSee('Shipped Model')
        ->assertDontSee('Received Model');

    $component->set('tab', 'inquiring')
        ->assertSee('Inquiring Model')
        ->assertDontSee('New Model')
        ->assertDontSee('Quoted Model');

    $component->set('tab', 'quoted')
        ->assertSee('Quoted Model')
        ->assertDontSee('Inquiring Model')
        ->assertDontSee('Paid Model');

    // order_confirmed bundles paid + ordered_to_vendor + procurement_failed
    // (see RequestBoard::tabStatuses()).
    $component->set('tab', 'order_confirmed')
        ->assertSee('Paid Model')
        ->assertSee('Ordered Model')
        ->assertSee('Failed Model')
        ->assertDontSee('Quoted Model')
        ->assertDontSee('Shipped Model');

    $component->set('tab', 'shipped')
        ->assertSee('Shipped Model')
        ->assertDontSee('Paid Model')
        ->assertDontSee('Received Model');

    $component->set('tab', 'completed')
        ->assertSee('Received Model')
        ->assertDontSee('Shipped Model');

    $component->set('tab', 'all')
        ->assertSee('New Model')
        ->assertSee('Inquiring Model')
        ->assertSee('Quoted Model')
        ->assertSee('Paid Model')
        ->assertSee('Ordered Model')
        ->assertSee('Failed Model')
        ->assertSee('Shipped Model')
        ->assertSee('Received Model');

    expect($new->status)->toBe(RequestStatus::New)
        ->and($inquiring->status)->toBe(RequestStatus::VendorInquiry)
        ->and($quoted->status)->toBe(RequestStatus::Quoted)
        ->and($paid->status)->toBe(RequestStatus::Paid)
        ->and($orderedToVendor->status)->toBe(RequestStatus::OrderedToVendor)
        ->and($procurementFailed->status)->toBe(RequestStatus::ProcurementFailed)
        ->and($shipped->status)->toBe(RequestStatus::Shipped)
        ->and($received->status)->toBe(RequestStatus::Received);
});

it('lists an awaiting_payment request under the quoted tab, labelled as awaiting payment -- not under order confirmed', function () {
    $admin = User::factory()->admin()->create();
    PartRequest::factory()->create(['status' => RequestStatus::AwaitingPayment, 'car_model' => 'Awaiting Model']);

    $component = Livewire::actingAs($admin)->test(RequestBoard::class);

    $component->set('tab', 'quoted')
        ->assertSee('Awaiting Model')
        ->assertSee(__('admin.request_board.status.awaiting_payment'));

    $component->set('tab', 'order_confirmed')
        ->assertDontSee('Awaiting Model');
});
