<?php

use App\Actions\SubmitPartRequestAction;
use App\Enums\PartType;
use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a request with a request_code derived from its own id', function () {
    $buyer = BuyerProfile::factory()->create();

    $request = app(SubmitPartRequestAction::class)->execute(
        $buyer,
        PartType::Used,
        'Toyota',
        'Crown',
        'GRS184-0002255',
        '2005/10',
        '81110-60M00',
        'Right LED headlight',
        'https://example.com/listing',
        'Please prioritize speed',
    );

    expect($request->request_code)->toBe(PartRequest::generateRequestCode($request->id))
        ->and($request->buyer_id)->toBe($buyer->id)
        ->and($request->status)->toBe(RequestStatus::New)
        ->and($request->oem_part_number)->toBe('81110-60M00')
        ->and($request->reference_url)->toBe('https://example.com/listing')
        ->and($request->memo)->toBe('Please prioritize speed')
        // Stored exactly as given -- a plain string, year and month only,
        // never cast to/from a real date (see the part_requests migration).
        ->and($request->mfg_date)->toBe('2005/10');
});

it('allows null optional fields', function () {
    $buyer = BuyerProfile::factory()->create();

    $request = app(SubmitPartRequestAction::class)->execute(
        $buyer,
        PartType::Both,
        'Nissan',
        'Skyline',
        null,
        null,
        null,
        'Rear bumper',
        null,
        null,
    );

    expect($request->vin)->toBeNull()
        ->and($request->mfg_date)->toBeNull()
        ->and($request->oem_part_number)->toBeNull()
        ->and($request->reference_url)->toBeNull()
        ->and($request->memo)->toBeNull();
});

it('never collides on request_code across multiple requests, even with the null placeholder mid-creation', function () {
    $buyer = BuyerProfile::factory()->create();

    $requests = collect(range(1, 5))->map(fn () => app(SubmitPartRequestAction::class)->execute(
        $buyer, PartType::Used, 'Toyota', 'Crown', null, null, null, 'Part', null, null,
    ));

    expect($requests->pluck('request_code')->unique())->toHaveCount(5);
});
