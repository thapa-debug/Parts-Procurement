<?php

use App\Models\Maker;
use Database\Seeders\MakerSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a maker with the expected defaults and casts', function () {
    $maker = Maker::factory()->create(['name' => 'Toyota']);

    expect($maker->name)->toBe('Toyota')
        ->and($maker->is_active)->toBeTrue();
});

it('enforces a unique name', function () {
    $existing = Maker::factory()->create();

    $attempt = fn () => Maker::factory()->create(['name' => $existing->name]);

    expect($attempt)->toThrow(QueryException::class);
});

it('excludes inactive makers from the active scope', function () {
    $active = Maker::factory()->create();
    $inactive = Maker::factory()->inactive()->create();

    $activeIds = Maker::query()->active()->pluck('id')->all();

    expect($activeIds)->toContain($active->id)
        ->and($activeIds)->not->toContain($inactive->id);
});

it('seeds the starting list as active', function () {
    $this->seed(MakerSeeder::class);

    expect(Maker::query()->active()->pluck('name')->sort()->values()->all())
        ->toBe([
            'Daihatsu', 'Honda', 'Imported / Other', 'Mazda', 'Mitsubishi',
            'Nissan', 'Subaru', 'Suzuki', 'Toyota',
        ]);
});
