<?php

use App\Actions\SubmitVendorResponseAction;
use App\Enums\LeadTime;
use App\Enums\QualityRank;
use App\Exceptions\VendorResponseNotAllowedException;
use App\Models\PartRequest;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('records a priced quote with its photos for a vendor actually invited to the request', function () {
    // Pin the disk regardless of the developer's own local FILESYSTEM_DISK
    // override (CONVENTIONS.md "Local dev without S3") -- this test proves
    // the real production disk works, not whatever's ambient locally.
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    $request = PartRequest::factory()->create();
    $vendor = VendorProfile::factory()->create();
    $request->vendors()->attach($vendor->id, ['invited_at' => now()]);

    $photo = UploadedFile::fake()->image('bumper.jpg');

    $response = app(SubmitVendorResponseAction::class)->execute($request, $vendor, [
        'cost_price' => 45_000,
        'quality_rank' => QualityRank::A->value,
        'lead_time' => LeadTime::Within1Week->value,
        'comment' => 'Clean, no visible damage.',
        'weight_kg' => 3.5,
        'length_cm' => 40,
        'width_cm' => 25,
        'height_cm' => 15,
    ], [$photo]);

    expect($response->part_request_id)->toBe($request->id)
        ->and($response->vendor_id)->toBe($vendor->id)
        ->and($response->cost_price)->toBe(45_000)
        ->and($response->quality_rank)->toBe(QualityRank::A)
        ->and($response->lead_time)->toBe(LeadTime::Within1Week)
        ->and($response->weight_kg)->toBe('3.50')
        ->and($response->length_cm)->toBe('40.00')
        ->and($response->width_cm)->toBe('25.00')
        ->and($response->height_cm)->toBe('15.00')
        ->and($response->is_no_stock)->toBeFalse()
        ->and($response->photos)->toHaveCount(1);

    Storage::disk('s3')->assertExists($response->photos->first()->path);
});

it('records every photo passed, not just the first, each stored on the same disk', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    $request = PartRequest::factory()->create();
    $vendor = VendorProfile::factory()->create();
    $request->vendors()->attach($vendor->id, ['invited_at' => now()]);

    $photos = [
        UploadedFile::fake()->image('front.jpg'),
        UploadedFile::fake()->image('side.jpg'),
        UploadedFile::fake()->image('damage-closeup.jpg'),
    ];

    $response = app(SubmitVendorResponseAction::class)->execute($request, $vendor, [
        'cost_price' => 45_000,
        'quality_rank' => QualityRank::A->value,
        'lead_time' => LeadTime::Within1Week->value,
        'comment' => 'Clean, no visible damage.',
    ], $photos);

    expect($response->photos)->toHaveCount(3)
        ->and($response->photos->pluck('disk')->unique()->all())->toBe(['s3']);

    foreach ($response->photos as $photo) {
        Storage::disk('s3')->assertExists($photo->path);
    }
});

it('records a one-tap no-stock reply with no price, rank, lead time, weight, dimensions, or photos', function () {
    $request = PartRequest::factory()->create();
    $vendor = VendorProfile::factory()->create();
    $request->vendors()->attach($vendor->id, ['invited_at' => now()]);

    $response = app(SubmitVendorResponseAction::class)->execute($request, $vendor, ['is_no_stock' => true]);

    expect($response->is_no_stock)->toBeTrue()
        ->and($response->cost_price)->toBeNull()
        ->and($response->quality_rank)->toBeNull()
        ->and($response->lead_time)->toBeNull()
        ->and($response->weight_kg)->toBeNull()
        ->and($response->length_cm)->toBeNull()
        ->and($response->width_cm)->toBeNull()
        ->and($response->height_cm)->toBeNull()
        ->and($response->photos)->toHaveCount(0);
});

it('refuses a response from a vendor never invited to the request', function () {
    $request = PartRequest::factory()->create();
    $uninvitedVendor = VendorProfile::factory()->create();

    $attempt = fn () => app(SubmitVendorResponseAction::class)->execute($request, $uninvitedVendor, ['is_no_stock' => true]);

    expect($attempt)->toThrow(VendorResponseNotAllowedException::class);
    expect(VendorResponse::count())->toBe(0);
});

it('refuses a second response from a vendor who already responded to this request', function () {
    $request = PartRequest::factory()->create();
    $vendor = VendorProfile::factory()->create();
    $request->vendors()->attach($vendor->id, ['invited_at' => now()]);

    app(SubmitVendorResponseAction::class)->execute($request, $vendor, ['is_no_stock' => true]);

    $attempt = fn () => app(SubmitVendorResponseAction::class)->execute($request, $vendor, ['is_no_stock' => true]);

    expect($attempt)->toThrow(VendorResponseNotAllowedException::class);
    expect(VendorResponse::count())->toBe(1);
});

it('lets a different invited vendor on the same request still respond independently', function () {
    $request = PartRequest::factory()->create();
    $vendorA = VendorProfile::factory()->create();
    $vendorB = VendorProfile::factory()->create();
    $request->vendors()->attach([$vendorA->id, $vendorB->id], ['invited_at' => now()]);

    app(SubmitVendorResponseAction::class)->execute($request, $vendorA, [
        'cost_price' => 30_000,
        'quality_rank' => QualityRank::S->value,
        'lead_time' => LeadTime::Within2Days->value,
        'comment' => 'A',
    ]);

    $responseB = app(SubmitVendorResponseAction::class)->execute($request, $vendorB, [
        'cost_price' => 50_000,
        'quality_rank' => QualityRank::B->value,
        'lead_time' => LeadTime::Within2Weeks->value,
        'comment' => 'B',
    ]);

    expect(VendorResponse::count())->toBe(2)
        ->and($responseB->vendor_id)->toBe($vendorB->id)
        ->and($responseB->cost_price)->toBe(50_000);
});

it('rolls back the whole response -- no row and no photos -- if the transaction fails', function () {
    // Pin the disk regardless of the developer's own local FILESYSTEM_DISK
    // override (CONVENTIONS.md "Local dev without S3") -- this test proves
    // the real production disk works, not whatever's ambient locally.
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    $request = PartRequest::factory()->create();
    $vendor = VendorProfile::factory()->create();
    $request->vendors()->attach($vendor->id, ['invited_at' => now()]);

    VendorResponse::creating(function () {
        throw new RuntimeException('forced failure for test');
    });

    $attempt = fn () => app(SubmitVendorResponseAction::class)->execute($request, $vendor, [
        'cost_price' => 45_000,
        'quality_rank' => QualityRank::A->value,
        'lead_time' => LeadTime::Within1Week->value,
        'comment' => 'x',
    ], [UploadedFile::fake()->image('bumper.jpg')]);

    expect($attempt)->toThrow(RuntimeException::class);
    expect(VendorResponse::count())->toBe(0);
});
