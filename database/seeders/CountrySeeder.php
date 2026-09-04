<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

/**
 * Real reference data the admin manages going forward (client revision --
 * country was previously free text on buyer registration), not demo/fixture
 * data -- runs in every environment, ahead of DemoSeeder, not folded into it.
 * Placeholder starting list; the admin adds more via Settings.
 */
class CountrySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Australia', 'New Zealand'] as $name) {
            Country::query()->firstOrCreate(['name' => $name]);
        }
    }
}
