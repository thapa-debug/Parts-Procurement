<?php

use App\Enums\PartType;
use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- model / factory sanity -------------------------------------------------

it('creates a part request with the expected defaults and enum casts', function () {
    $request = PartRequest::factory()->create();

    expect($request->status)->toBe(RequestStatus::New)
        ->and($request->part_type)->toBeInstanceOf(PartType::class)
        ->and($request->buyer)->toBeInstanceOf(BuyerProfile::class)
        ->and($request->applied_rate)->toBeNull()
        ->and($request->applied_min_fee)->toBeNull()
        ->and($request->buyer_price)->toBeNull()
        ->and($request->shipping_method)->toBeNull()
        ->and($request->shipping_fee)->toBeNull();
});

it('enforces a unique request_code', function () {
    $existing = PartRequest::factory()->create();

    $attempt = fn () => PartRequest::factory()->create(['request_code' => $existing->request_code]);

    expect($attempt)->toThrow(QueryException::class);
});

it('derives a request code deterministically from the row id', function () {
    expect(PartRequest::generateRequestCode(42))->toBe('REQ-000042');
});

// --- PartRequestPolicy -------------------------------------------------------

it('lets admins and buyers view the request list, but not vendors', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();

    expect($admin->can('viewAny', PartRequest::class))->toBeTrue()
        ->and($buyer->can('viewAny', PartRequest::class))->toBeTrue()
        ->and($vendor->can('viewAny', PartRequest::class))->toBeFalse();
});

it('lets a buyer view only their own request, never another buyer\'s or a vendor\'s view of it', function () {
    $owner = User::factory()->buyer()->create();
    $ownerProfile = BuyerProfile::factory()->for($owner)->create();
    $request = PartRequest::factory()->for($ownerProfile, 'buyer')->create();

    $otherBuyer = User::factory()->buyer()->create();
    BuyerProfile::factory()->for($otherBuyer)->create();

    $admin = User::factory()->admin()->create();
    $vendor = User::factory()->vendor()->create();

    expect($owner->can('view', $request))->toBeTrue()
        ->and($otherBuyer->can('view', $request))->toBeFalse()
        ->and($vendor->can('view', $request))->toBeFalse()
        ->and($admin->can('view', $request))->toBeTrue();
});

it('lets only a buyer create a request, structurally -- the act gate governs whether this specific buyer may right now', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();

    expect($buyer->can('create', PartRequest::class))->toBeTrue()
        ->and($admin->can('create', PartRequest::class))->toBeFalse()
        ->and($vendor->can('create', PartRequest::class))->toBeFalse();
});

it('never allows direct model updates -- status transitions are owned by guarded Actions, not raw writes', function () {
    $admin = User::factory()->admin()->create();
    $request = PartRequest::factory()->create();

    expect($admin->can('update', $request))->toBeFalse();
});
