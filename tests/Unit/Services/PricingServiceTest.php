<?php

use App\Models\Setting;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedMarginSettings(int $rate, int $minFee): void
{
    Setting::set('margin_rate', $rate, 'integer');
    Setting::set('margin_min_fee', $minFee, 'integer');
}

it('applies the minimum fee floor when the percentage margin falls below it', function () {
    seedMarginSettings(rate: 10, minFee: 2000);

    $result = app(PricingService::class)->calculate(costPrice: 10_000);

    // percentage margin = round(10,000 * 10 / 100) = 1,000 < floor 2,000 -> floor wins
    expect($result['applied_rate'])->toBe(10)
        ->and($result['applied_min_fee'])->toBe(2000)
        ->and($result['margin'])->toBe(2000)
        ->and($result['buyer_price'])->toBe(12_000);
});

it('applies the floor when the percentage margin exactly equals it', function () {
    seedMarginSettings(rate: 20, minFee: 2000);

    $result = app(PricingService::class)->calculate(costPrice: 10_000);

    // percentage margin = round(10,000 * 20 / 100) = 2,000 == floor 2,000
    expect($result['margin'])->toBe(2000)
        ->and($result['buyer_price'])->toBe(12_000);
});

it('applies the percentage margin when it exceeds the minimum fee floor', function () {
    seedMarginSettings(rate: 20, minFee: 2000);

    $result = app(PricingService::class)->calculate(costPrice: 50_000);

    // percentage margin = round(50,000 * 20 / 100) = 10,000 > floor 2,000 -> percentage wins
    expect($result['margin'])->toBe(10_000)
        ->and($result['buyer_price'])->toBe(60_000);
});

it('holds max(percentage, floor) for any configured rate and minimum fee', function (
    int $costPrice,
    int $rate,
    int $minFee,
    int $expectedMargin,
) {
    seedMarginSettings($rate, $minFee);

    $result = app(PricingService::class)->calculate($costPrice);

    expect($result['margin'])->toBe($expectedMargin)
        ->and($result['buyer_price'])->toBe($costPrice + $expectedMargin)
        ->and($result['cost_price'])->toBe($costPrice)
        ->and($result['applied_rate'])->toBe($rate)
        ->and($result['applied_min_fee'])->toBe($minFee);
})->with([
    'low-cost part, floor wins' => [5_000, 15, 3000, 3000],
    'boundary, percentage == floor' => [20_000, 25, 5000, 5000],
    'high-cost part, percentage wins' => [200_000, 30, 5000, 60_000],
    'zero-rate edge case, floor always wins' => [100_000, 0, 2000, 2000],
]);
