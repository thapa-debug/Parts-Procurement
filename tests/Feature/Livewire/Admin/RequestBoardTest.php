<?php

use App\Enums\RequestStatus;
use App\Livewire\Admin\RequestBoard;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

// --- status tabs -----------------------------------------------------------

it('filters requests into the correct tab by status, with accurate counts', function () {
    $admin = User::factory()->admin()->create();

    $new = PartRequest::factory()->create(['status' => RequestStatus::New, 'car_model' => 'New Model']);
    $inProgress = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry, 'car_model' => 'Progress Model']);
    $purchased = PartRequest::factory()->create(['status' => RequestStatus::Paid, 'car_model' => 'Purchased Model']);
    $completed = PartRequest::factory()->create(['status' => RequestStatus::Received, 'car_model' => 'Completed Model']);

    $component = Livewire::actingAs($admin)->test(RequestBoard::class);

    $component->set('tab', 'new')
        ->assertSee('New Model')
        ->assertDontSee('Progress Model')
        ->assertDontSee('Purchased Model')
        ->assertDontSee('Completed Model');

    $component->set('tab', 'in_progress')
        ->assertSee('Progress Model')
        ->assertDontSee('New Model');

    $component->set('tab', 'purchased')
        ->assertSee('Purchased Model')
        ->assertDontSee('Progress Model');

    $component->set('tab', 'completed')
        ->assertSee('Completed Model')
        ->assertDontSee('Purchased Model');

    $component->set('tab', 'all')
        ->assertSee('New Model')
        ->assertSee('Progress Model')
        ->assertSee('Purchased Model')
        ->assertSee('Completed Model');

    expect($new->status)->toBe(RequestStatus::New)
        ->and($inProgress->status)->toBe(RequestStatus::VendorInquiry)
        ->and($purchased->status)->toBe(RequestStatus::Paid)
        ->and($completed->status)->toBe(RequestStatus::Received);
});
