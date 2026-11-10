<?php

use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Which admin screens use the wide layout container (->layoutData(['wide'
 * => true]), layouts/app.blade.php). Dense list/table/detail screens are
 * wide; forms and simple pages keep the standard max-w-6xl, since forms
 * read worse stretched across 1536px.
 */

const WIDE_MAIN = '<main class="mx-auto max-w-[96rem] px-6 py-10">';
const STANDARD_MAIN = '<main class="mx-auto max-w-6xl px-6 py-10">';

it('renders dense admin list/detail screens in the wide container', function (Closure $url) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get($url())
        ->assertOk()
        ->assertSee(WIDE_MAIN, false);
})->with([
    'request board' => fn () => route('admin.requests.index'),
    'request detail' => fn () => route('admin.requests.show', PartRequest::factory()->create()),
    'vendor master' => fn () => route('admin.vendors.index'),
    'buyer master' => fn () => route('admin.buyers.index'),
    // Each settings section stays capped at max-w-2xl; the wide frame only
    // keeps the menu + nav aligned with the other admin screens.
    'settings' => fn () => route('admin.settings'),
]);

it('keeps form-centric admin screens at the standard width', function (Closure $url) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get($url())
        ->assertOk()
        ->assertSee(STANDARD_MAIN, false);
})->with([
    'vendor detail' => fn () => route('admin.vendors.show', VendorProfile::factory()->create()),
    'buyer detail' => fn () => route('admin.buyers.show', BuyerProfile::factory()->create()),
]);
