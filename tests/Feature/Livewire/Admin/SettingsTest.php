<?php

use App\Livewire\Admin\Settings;
use App\Models\Setting;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// --- authorization: a non-admin cannot even mount the component ------------

it('does not let a buyer mount the settings component', function () {
    $buyer = User::factory()->buyer()->create();

    Livewire::actingAs($buyer)->test(Settings::class)->assertForbidden();
});

it('does not let a vendor mount the settings component', function () {
    $vendor = User::factory()->vendor()->create();

    Livewire::actingAs($vendor)->test(Settings::class)->assertForbidden();
});

it('lets an admin mount the settings component', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertSee(__('admin.settings.heading'));
});

// --- loading current values --------------------------------------------

it('pre-fills the PricingService defaults when nothing has been configured yet', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertSet('margin_rate', 20)
        ->assertSet('margin_min_fee', 2000)
        ->assertSet('shipping_fee_vehicle', 0)
        ->assertSet('shipping_fee_container', 0)
        ->assertSet('admin_sender_email', '');
});

it('loads existing stored values instead of the defaults', function () {
    Setting::set('margin_rate', 25, 'integer');
    Setting::set('margin_min_fee', 3000, 'integer');
    Setting::set('admin_sender_email', 'orders@example.com', 'string');

    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertSet('margin_rate', 25)
        ->assertSet('margin_min_fee', 3000)
        ->assertSet('admin_sender_email', 'orders@example.com');
});

// --- saving --------------------------------------------------------------

it('saves all five settings and shows the saved indicator', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('margin_rate', 30)
        ->set('margin_min_fee', 2500)
        ->set('shipping_fee_vehicle', 80000)
        ->set('shipping_fee_container', 150000)
        ->set('admin_sender_email', 'orders@example.com')
        ->call('save')
        ->assertSet('justSaved', true);

    expect(Setting::get('margin_rate'))->toBe(30)
        ->and(Setting::get('margin_min_fee'))->toBe(2500)
        ->and(Setting::get('shipping_fee_vehicle'))->toBe(80000)
        ->and(Setting::get('shipping_fee_container'))->toBe(150000)
        ->and(Setting::get('admin_sender_email'))->toBe('orders@example.com');
});

it('never writes a shipping_fee_dhl setting -- DHL is per-request, not a global fixed fee', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('admin_sender_email', 'orders@example.com')
        ->call('save');

    expect(Setting::get('shipping_fee_dhl'))->toBeNull();
});

it('clears the saved indicator as soon as a field changes again', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('admin_sender_email', 'orders@example.com')
        ->call('save')
        ->assertSet('justSaved', true)
        ->set('margin_rate', 21)
        ->assertSet('justSaved', false);
});

// --- validation ------------------------------------------------------------

it('rejects a negative margin rate', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('margin_rate', -5)
        ->set('admin_sender_email', 'orders@example.com')
        ->call('save')
        ->assertHasErrors(['margin_rate']);
});

it('rejects an invalid sender email', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('admin_sender_email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['admin_sender_email']);
});

// --- the whole point: PricingService actually reflects the new config -------

it('changes what PricingService calculates once settings are saved', function () {
    $admin = User::factory()->admin()->create();

    $before = app(PricingService::class)->calculate(10000);
    expect($before['applied_rate'])->toBe(20)
        ->and($before['applied_min_fee'])->toBe(2000);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('margin_rate', 35)
        ->set('margin_min_fee', 5000)
        ->set('admin_sender_email', 'orders@example.com')
        ->call('save');

    $after = app(PricingService::class)->calculate(10000);
    expect($after['applied_rate'])->toBe(35)
        ->and($after['applied_min_fee'])->toBe(5000)
        ->and($after['margin'])->toBe(5000)
        ->and($after['buyer_price'])->toBe(15000);
});
