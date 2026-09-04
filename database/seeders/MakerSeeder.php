<?php

namespace Database\Seeders;

use App\Models\Maker;
use Illuminate\Database\Seeder;

/**
 * Real reference data the admin manages going forward (client revision --
 * maker was previously a fixed list in lang/en/buyer.php), not demo/fixture
 * data -- runs in every environment, ahead of DemoSeeder, not folded into it.
 * The starting list is the app's previous hardcoded set; the admin adds more
 * via Settings.
 */
class MakerSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Toyota', 'Nissan', 'Honda', 'Mazda', 'Subaru',
            'Mitsubishi', 'Suzuki', 'Daihatsu', 'Imported / Other',
        ] as $name) {
            Maker::query()->firstOrCreate(['name' => $name]);
        }
    }
}
