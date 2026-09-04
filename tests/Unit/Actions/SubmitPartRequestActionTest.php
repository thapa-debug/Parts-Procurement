<?php

use App\Actions\SubmitPartRequestAction;
use App\Enums\PartType;
use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\Maker;
use App\Models\PartRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a request with a request_code derived from its own id', function () {
    $buyer = BuyerProfile::factory()->create();
    $maker = Maker::factory()->create(['name' => 'Toyota']);

    $request = app(SubmitPartRequestAction::class)->execute(
        $buyer,
        PartType::Used,
        $maker->id,
        'Crown',
        'GRS184-0002255',
        '81110-60M00',
        'Right LED headlight',
        'https://example.com/listing',
        'Please prioritize speed',
    );

    expect($request->request_code)->toBe(PartRequest::generateRequestCode($request->id))
        ->and($request->buyer_id)->toBe($buyer->id)
        ->and($request->status)->toBe(RequestStatus::New)
        ->and($request->vin)->toBe('GRS184-0002255')
        ->and($request->oem_part_number)->toBe('81110-60M00')
        ->and($request->reference_url)->toBe('https://example.com/listing')
        ->and($request->memo)->toBe('Please prioritize speed');
});

it('allows null oem_part_number, reference_url, and memo -- vin stays required', function () {
    $buyer = BuyerProfile::factory()->create();
    $maker = Maker::factory()->create(['name' => 'Nissan']);

    $request = app(SubmitPartRequestAction::class)->execute(
        $buyer,
        PartType::Both,
        $maker->id,
        'Skyline',
        'BNR34-123456',
        null,
        'Rear bumper',
        null,
        null,
    );

    expect($request->vin)->toBe('BNR34-123456')
        ->and($request->oem_part_number)->toBeNull()
        ->and($request->reference_url)->toBeNull()
        ->and($request->memo)->toBeNull();
});

it('never collides on request_code across multiple requests, even with the null placeholder mid-creation', function () {
    $buyer = BuyerProfile::factory()->create();
    $maker = Maker::factory()->create(['name' => 'Toyota']);

    $requests = collect(range(1, 5))->map(fn () => app(SubmitPartRequestAction::class)->execute(
        $buyer, PartType::Used, $maker->id, 'Crown', 'GRS184-0002255', null, 'Part', null, null,
    ));

    expect($requests->pluck('request_code')->unique())->toHaveCount(5);
});
