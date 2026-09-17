<?php

use App\Exceptions\ShippingBracketNotConfiguredException;
use App\Models\ShippingWeightBracket;
use App\Services\ShippingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Clears the migration's own default-seeded brackets first, so every test
 * here is only ever exercising the exact brackets it sets up itself.
 */
function freshBrackets(): void
{
    ShippingWeightBracket::query()->delete();
}

it('picks the lowest bracket when the weight falls below it -- no floor beneath the cheapest tier', function () {
    freshBrackets();
    ShippingWeightBracket::factory()->create(['upper_kg' => 5, 'fee' => 3_000, 'order' => 1]);
    ShippingWeightBracket::factory()->create(['upper_kg' => 20, 'fee' => 8_000, 'order' => 2]);

    expect(app(ShippingCalculator::class)->calculate(0.5))->toBe(3_000);
});

it('picks the bracket exactly on its own upper bound, not the next one up', function () {
    freshBrackets();
    ShippingWeightBracket::factory()->create(['upper_kg' => 5, 'fee' => 3_000, 'order' => 1]);
    ShippingWeightBracket::factory()->create(['upper_kg' => 20, 'fee' => 8_000, 'order' => 2]);

    expect(app(ShippingCalculator::class)->calculate(5.0))->toBe(3_000);
});

it('picks the next bracket up for a weight strictly between two bounds', function () {
    freshBrackets();
    ShippingWeightBracket::factory()->create(['upper_kg' => 5, 'fee' => 3_000, 'order' => 1]);
    ShippingWeightBracket::factory()->create(['upper_kg' => 20, 'fee' => 8_000, 'order' => 2]);
    ShippingWeightBracket::factory()->create(['upper_kg' => 40, 'fee' => 15_000, 'order' => 3]);

    expect(app(ShippingCalculator::class)->calculate(12.3))->toBe(8_000);
});

it('falls into the null-upper_kg catch-all bracket for a weight above every finite bound', function () {
    freshBrackets();
    ShippingWeightBracket::factory()->create(['upper_kg' => 5, 'fee' => 3_000, 'order' => 1]);
    ShippingWeightBracket::factory()->create(['upper_kg' => 20, 'fee' => 8_000, 'order' => 2]);
    ShippingWeightBracket::factory()->catchAll()->create(['fee' => 120_000, 'order' => 3]);

    expect(app(ShippingCalculator::class)->calculate(450.0))->toBe(120_000);
});

it('throws rather than silently charging ¥0 when no bracket covers the weight at all', function () {
    freshBrackets();
    ShippingWeightBracket::factory()->create(['upper_kg' => 5, 'fee' => 3_000, 'order' => 1]);
    // No catch-all bracket -- a weight above 5kg has nothing to match.

    $attempt = fn () => app(ShippingCalculator::class)->calculate(6.0);

    expect($attempt)->toThrow(ShippingBracketNotConfiguredException::class);
});

it('throws when shipping_weight_brackets is completely empty', function () {
    freshBrackets();

    $attempt = fn () => app(ShippingCalculator::class)->calculate(1.0);

    expect($attempt)->toThrow(ShippingBracketNotConfiguredException::class);
});

it('is unaffected by the `order` values not matching upper_kg\'s own ascending order, as long as order is correct', function () {
    // Defensive: proves the lookup follows `order`, not upper_kg itself,
    // in case the two are ever set inconsistently by mistake.
    freshBrackets();
    ShippingWeightBracket::factory()->create(['upper_kg' => 20, 'fee' => 8_000, 'order' => 1]);
    ShippingWeightBracket::factory()->create(['upper_kg' => 5, 'fee' => 3_000, 'order' => 2]);

    // Weight 3kg matches both brackets (both upper_kg >= 3) -- `order`
    // ascending picks the upper_kg=20 one first here, since it's order 1.
    expect(app(ShippingCalculator::class)->calculate(3.0))->toBe(8_000);
});
