<?php

use App\Actions\BroadcastRequestAction;
use App\Enums\RequestStatus;
use App\Exceptions\RequestCannotBeBroadcastException;
use App\Models\PartRequest;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('broadcasts to the selected active vendors, records invited_at, and transitions to vendor_inquiry', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::New]);
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();

    $result = app(BroadcastRequestAction::class)->execute($request, [$vendorA->id, $vendorB->id]);

    expect($result->status)->toBe(RequestStatus::VendorInquiry)
        ->and($result->vendors()->pluck('vendor_profiles.id')->sort()->values()->all())
        ->toBe([$vendorA->id, $vendorB->id]);

    foreach ([$vendorA, $vendorB] as $vendor) {
        $pivot = $result->vendors()->where('vendor_profiles.id', $vendor->id)->first()->pivot;
        expect($pivot->invited_at)->not->toBeNull();
    }
});

it('excludes a suspended vendor from the broadcast, even if its id was submitted', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::New]);
    $active = VendorProfile::factory()->create();
    $suspended = VendorProfile::factory()->suspended()->create();

    $result = app(BroadcastRequestAction::class)->execute($request, [$active->id, $suspended->id]);

    $invitedIds = $result->vendors()->pluck('vendor_profiles.id')->all();

    expect($invitedIds)->toBe([$active->id])
        ->and($invitedIds)->not->toContain($suspended->id);
});

it('throws and invites no one when every submitted vendor is suspended', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::New]);
    $suspended = VendorProfile::factory()->suspended()->create();

    $attempt = fn () => app(BroadcastRequestAction::class)->execute($request, [$suspended->id]);

    expect($attempt)->toThrow(RequestCannotBeBroadcastException::class);

    expect($request->fresh()->status)->toBe(RequestStatus::New)
        ->and($request->fresh()->vendors)->toHaveCount(0);
});

it('throws when no vendors are submitted at all', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::New]);

    $attempt = fn () => app(BroadcastRequestAction::class)->execute($request, []);

    expect($attempt)->toThrow(RequestCannotBeBroadcastException::class);
});

it('refuses to broadcast a request that is not new', function (RequestStatus $status) {
    $request = PartRequest::factory()->create(['status' => $status]);
    $vendor = VendorProfile::factory()->create();

    $attempt = fn () => app(BroadcastRequestAction::class)->execute($request, [$vendor->id]);

    expect($attempt)->toThrow(RequestCannotBeBroadcastException::class);

    expect($request->fresh()->vendors)->toHaveCount(0);
})->with([
    'already inquiring' => RequestStatus::VendorInquiry,
    'quoted' => RequestStatus::Quoted,
    'paid' => RequestStatus::Paid,
]);

it('rolls back the whole broadcast -- no pivot rows and no status change -- if the transaction fails', function () {
    $request = PartRequest::factory()->create(['status' => RequestStatus::New]);
    $vendor = VendorProfile::factory()->create();

    PartRequest::updating(function () {
        throw new RuntimeException('forced failure for test');
    });

    $attempt = fn () => app(BroadcastRequestAction::class)->execute($request, [$vendor->id]);

    expect($attempt)->toThrow(RuntimeException::class);

    expect($request->fresh()->status)->toBe(RequestStatus::New)
        ->and($request->fresh()->vendors)->toHaveCount(0);
});
