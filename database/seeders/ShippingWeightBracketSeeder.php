<?php

namespace Database\Seeders;

use App\Models\ShippingWeightBracket;
use Illuminate\Database\Seeder;

/**
 * Resets shipping_weight_brackets to the same sensible starting values the
 * create_shipping_weight_brackets_table migration already seeds
 * automatically -- useful for restoring the defaults if an admin clears
 * the table via the (not yet built) Settings UI. Not run automatically by
 * DatabaseSeeder; the migration already guarantees the table is never
 * empty from the start.
 */
class ShippingWeightBracketSeeder extends Seeder
{
    public function run(): void
    {
        ShippingWeightBracket::query()->delete();

        collect([
            ['upper_kg' => 5, 'fee' => 3_000, 'order' => 1],
            ['upper_kg' => 10, 'fee' => 5_000, 'order' => 2],
            ['upper_kg' => 20, 'fee' => 8_000, 'order' => 3],
            ['upper_kg' => 40, 'fee' => 15_000, 'order' => 4],
            ['upper_kg' => 80, 'fee' => 25_000, 'order' => 5],
            ['upper_kg' => 150, 'fee' => 40_000, 'order' => 6],
            ['upper_kg' => 300, 'fee' => 70_000, 'order' => 7],
            ['upper_kg' => null, 'fee' => 120_000, 'order' => 8],
        ])->each(fn (array $bracket) => ShippingWeightBracket::create($bracket));
    }
}
