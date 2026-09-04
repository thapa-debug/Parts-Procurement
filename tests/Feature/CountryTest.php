<?php

use App\Models\Country;
use Database\Seeders\CountrySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a country with the expected defaults and casts', function () {
    $country = Country::factory()->create(['name' => 'Australia']);

    expect($country->name)->toBe('Australia')
        ->and($country->is_active)->toBeTrue();
});

it('enforces a unique name', function () {
    $existing = Country::factory()->create();

    $attempt = fn () => Country::factory()->create(['name' => $existing->name]);

    expect($attempt)->toThrow(QueryException::class);
});

it('excludes inactive countries from the active scope', function () {
    $active = Country::factory()->create();
    $inactive = Country::factory()->inactive()->create();

    $activeIds = Country::query()->active()->pluck('id')->all();

    expect($activeIds)->toContain($active->id)
        ->and($activeIds)->not->toContain($inactive->id);
});

it('seeds the placeholder starting list as active', function () {
    $this->seed(CountrySeeder::class);

    expect(Country::query()->active()->pluck('name')->sort()->values()->all())
        ->toBe(['Australia', 'New Zealand']);
});
