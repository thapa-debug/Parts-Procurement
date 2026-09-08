<?php

use App\Livewire\Admin\Settings;
use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\Maker;
use App\Models\PartRequest;
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

// --- section navigation (client revision) -----------------------------

it('defaults to the general section, showing the margin form and hiding the country/maker lists', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertSet('activeSection', 'general')
        ->assertSee(__('admin.settings.save_button'))
        ->assertDontSee(__('admin.settings.add_country_button'))
        ->assertDontSee(__('admin.settings.add_maker_button'));
});

it('switches sections via showSection, rendering only the selected section', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('showSection', 'countries')
        ->assertSet('activeSection', 'countries')
        ->assertSee(__('admin.settings.add_country_button'))
        ->assertDontSee(__('admin.settings.save_button'))
        ->assertDontSee(__('admin.settings.add_maker_button'))
        ->call('showSection', 'makers')
        ->assertSet('activeSection', 'makers')
        ->assertSee(__('admin.settings.add_maker_button'))
        ->assertDontSee(__('admin.settings.save_button'))
        ->assertDontSee(__('admin.settings.add_country_button'));
});

it('falls back to the general section for an unrecognized section key', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('showSection', 'not-a-real-section')
        ->assertSet('activeSection', 'general');
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

// --- country management (client revision) -----------------------------

it('does not let a buyer or vendor add, edit, or toggle a country -- same gating as the rest of Settings', function () {
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();

    Livewire::actingAs($buyer)->test(Settings::class)->assertForbidden();
    Livewire::actingAs($vendor)->test(Settings::class)->assertForbidden();
});

it('lists every country, active and inactive, with its status', function () {
    $admin = User::factory()->admin()->create();
    Country::factory()->create(['name' => 'Active Land']);
    Country::factory()->inactive()->create(['name' => 'Inactive Land']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('activeSection', 'countries')
        ->assertSee('Active Land')
        ->assertSee('Inactive Land')
        ->assertSeeInOrder(['Active Land', __('admin.settings.country_status.active')])
        ->assertSeeInOrder(['Inactive Land', __('admin.settings.country_status.inactive')]);
});

it('adds a new country, active by default', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('activeSection', 'countries')
        ->set('new_country_name', 'Singapore')
        ->call('addCountry')
        ->assertHasNoErrors()
        ->assertSet('new_country_name', '')
        ->assertSee('Singapore');

    $country = Country::where('name', 'Singapore')->firstOrFail();
    expect($country->is_active)->toBeTrue();
});

it('rejects adding a duplicate country name', function () {
    $admin = User::factory()->admin()->create();
    Country::factory()->create(['name' => 'Singapore']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('new_country_name', 'Singapore')
        ->call('addCountry')
        ->assertHasErrors(['new_country_name']);

    expect(Country::where('name', 'Singapore')->count())->toBe(1);
});

it('edits a country\'s name', function () {
    $admin = User::factory()->admin()->create();
    $country = Country::factory()->create(['name' => 'Singapor']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('startEditingCountry', $country->id)
        ->assertSet('editing_country_name', 'Singapor')
        ->set('editing_country_name', 'Singapore')
        ->call('saveCountry')
        ->assertHasNoErrors()
        ->assertSet('editingCountryId', null);

    expect($country->fresh()->name)->toBe('Singapore');
});

it('rejects renaming a country to a name another country already has', function () {
    $admin = User::factory()->admin()->create();
    Country::factory()->create(['name' => 'Taken']);
    $country = Country::factory()->create(['name' => 'Original']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('startEditingCountry', $country->id)
        ->set('editing_country_name', 'Taken')
        ->call('saveCountry')
        ->assertHasErrors(['editing_country_name']);

    expect($country->fresh()->name)->toBe('Original');
});

it('lets editing a country keep its own current name unchanged', function () {
    $admin = User::factory()->admin()->create();
    $country = Country::factory()->create(['name' => 'Australia']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('startEditingCountry', $country->id)
        ->call('saveCountry')
        ->assertHasNoErrors();

    expect($country->fresh()->name)->toBe('Australia');
});

it('cancels editing without saving', function () {
    $admin = User::factory()->admin()->create();
    $country = Country::factory()->create(['name' => 'Australia']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('startEditingCountry', $country->id)
        ->set('editing_country_name', 'Should not save')
        ->call('cancelEditingCountry')
        ->assertSet('editingCountryId', null)
        ->assertSet('editing_country_name', '');

    expect($country->fresh()->name)->toBe('Australia');
});

it('toggles a country between active and inactive, without deleting it', function () {
    $admin = User::factory()->admin()->create();
    $country = Country::factory()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('toggleCountryActive', $country->id);

    expect($country->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('toggleCountryActive', $country->id);

    expect($country->fresh()->is_active)->toBeTrue();
});

it('filters the country list by name as the admin searches', function () {
    $admin = User::factory()->admin()->create();
    Country::factory()->create(['name' => 'Australia']);
    Country::factory()->create(['name' => 'Austria']);
    Country::factory()->create(['name' => 'New Zealand']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('activeSection', 'countries')
        ->set('countrySearch', 'Aust')
        ->assertSee('Australia')
        ->assertSee('Austria')
        ->assertDontSee('New Zealand');
});

it('shows a message when no country matches the search', function () {
    $admin = User::factory()->admin()->create();
    Country::factory()->create(['name' => 'Australia']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('activeSection', 'countries')
        ->set('countrySearch', 'Nowhere')
        ->assertSee(__('admin.settings.country_empty_search'))
        ->assertDontSee('Australia');
});

it('lists active countries before inactive ones, alphabetical within each group', function () {
    $admin = User::factory()->admin()->create();
    Country::factory()->inactive()->create(['name' => 'Zed Inactive']);
    Country::factory()->create(['name' => 'Zeta Active']);
    Country::factory()->create(['name' => 'Alpha Active']);
    Country::factory()->inactive()->create(['name' => 'Alpha Inactive']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('activeSection', 'countries')
        ->assertSeeInOrder(['Alpha Active', 'Zeta Active', 'Alpha Inactive', 'Zed Inactive']);
});

it('deactivating a country never deletes it or breaks an existing buyer\'s reference to it', function () {
    $admin = User::factory()->admin()->create();
    $country = Country::factory()->create();
    $profile = BuyerProfile::factory()->create(['country_id' => $country->id]);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('toggleCountryActive', $country->id);

    expect(Country::find($country->id))->not->toBeNull()
        ->and($profile->fresh()->country_id)->toBe($country->id)
        ->and($profile->fresh()->country->name)->toBe($country->name);
});

// --- maker management (client revision, mirrors countries above) ----------

it('does not let a buyer or vendor add, edit, or toggle a maker -- same gating as the rest of Settings', function () {
    $buyer = User::factory()->buyer()->create();
    $vendor = User::factory()->vendor()->create();

    Livewire::actingAs($buyer)->test(Settings::class)->assertForbidden();
    Livewire::actingAs($vendor)->test(Settings::class)->assertForbidden();
});

it('lists every maker, active and inactive, with its status', function () {
    $admin = User::factory()->admin()->create();
    Maker::factory()->create(['name' => 'Active Motors']);
    Maker::factory()->inactive()->create(['name' => 'Inactive Motors']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('activeSection', 'makers')
        ->assertSee('Active Motors')
        ->assertSee('Inactive Motors')
        ->assertSeeInOrder(['Active Motors', __('admin.settings.maker_status.active')])
        ->assertSeeInOrder(['Inactive Motors', __('admin.settings.maker_status.inactive')]);
});

it('adds a new maker, active by default', function () {
    $admin = User::factory()->admin()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('activeSection', 'makers')
        ->set('new_maker_name', 'Isuzu')
        ->call('addMaker')
        ->assertHasNoErrors()
        ->assertSet('new_maker_name', '')
        ->assertSee('Isuzu');

    $maker = Maker::where('name', 'Isuzu')->firstOrFail();
    expect($maker->is_active)->toBeTrue();
});

it('rejects adding a duplicate maker name', function () {
    $admin = User::factory()->admin()->create();
    Maker::factory()->create(['name' => 'Isuzu']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('new_maker_name', 'Isuzu')
        ->call('addMaker')
        ->assertHasErrors(['new_maker_name']);

    expect(Maker::where('name', 'Isuzu')->count())->toBe(1);
});

it('edits a maker\'s name', function () {
    $admin = User::factory()->admin()->create();
    $maker = Maker::factory()->create(['name' => 'Isuz']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('startEditingMaker', $maker->id)
        ->assertSet('editing_maker_name', 'Isuz')
        ->set('editing_maker_name', 'Isuzu')
        ->call('saveMaker')
        ->assertHasNoErrors()
        ->assertSet('editingMakerId', null);

    expect($maker->fresh()->name)->toBe('Isuzu');
});

it('rejects renaming a maker to a name another maker already has', function () {
    $admin = User::factory()->admin()->create();
    Maker::factory()->create(['name' => 'Taken']);
    $maker = Maker::factory()->create(['name' => 'Original']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('startEditingMaker', $maker->id)
        ->set('editing_maker_name', 'Taken')
        ->call('saveMaker')
        ->assertHasErrors(['editing_maker_name']);

    expect($maker->fresh()->name)->toBe('Original');
});

it('lets editing a maker keep its own current name unchanged', function () {
    $admin = User::factory()->admin()->create();
    $maker = Maker::factory()->create(['name' => 'Toyota']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('startEditingMaker', $maker->id)
        ->call('saveMaker')
        ->assertHasNoErrors();

    expect($maker->fresh()->name)->toBe('Toyota');
});

it('cancels editing a maker without saving', function () {
    $admin = User::factory()->admin()->create();
    $maker = Maker::factory()->create(['name' => 'Toyota']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('startEditingMaker', $maker->id)
        ->set('editing_maker_name', 'Should not save')
        ->call('cancelEditingMaker')
        ->assertSet('editingMakerId', null)
        ->assertSet('editing_maker_name', '');

    expect($maker->fresh()->name)->toBe('Toyota');
});

it('toggles a maker between active and inactive, without deleting it', function () {
    $admin = User::factory()->admin()->create();
    $maker = Maker::factory()->create();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('toggleMakerActive', $maker->id);

    expect($maker->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('toggleMakerActive', $maker->id);

    expect($maker->fresh()->is_active)->toBeTrue();
});

it('deactivating a maker never deletes it or breaks an existing part request\'s reference to it', function () {
    $admin = User::factory()->admin()->create();
    $maker = Maker::factory()->create();
    $partRequest = PartRequest::factory()->create(['maker_id' => $maker->id]);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->call('toggleMakerActive', $maker->id);

    expect(Maker::find($maker->id))->not->toBeNull()
        ->and($partRequest->fresh()->maker_id)->toBe($maker->id)
        ->and($partRequest->fresh()->maker->name)->toBe($maker->name);
});

it('filters the maker list by name as the admin searches', function () {
    $admin = User::factory()->admin()->create();
    Maker::factory()->create(['name' => 'Toyota']);
    Maker::factory()->create(['name' => 'Toyota Industries']);
    Maker::factory()->create(['name' => 'Nissan']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('activeSection', 'makers')
        ->set('makerSearch', 'Toyota')
        ->assertSee('Toyota')
        ->assertSee('Toyota Industries')
        ->assertDontSee('Nissan');
});

it('shows a message when no maker matches the search', function () {
    $admin = User::factory()->admin()->create();
    Maker::factory()->create(['name' => 'Toyota']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('activeSection', 'makers')
        ->set('makerSearch', 'Nowhere')
        ->assertSee(__('admin.settings.maker_empty_search'))
        ->assertDontSee('Toyota');
});

it('lists active makers before inactive ones, alphabetical within each group', function () {
    $admin = User::factory()->admin()->create();
    Maker::factory()->inactive()->create(['name' => 'Zed Inactive']);
    Maker::factory()->create(['name' => 'Zeta Active']);
    Maker::factory()->create(['name' => 'Alpha Active']);
    Maker::factory()->inactive()->create(['name' => 'Alpha Inactive']);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->set('activeSection', 'makers')
        ->assertSeeInOrder(['Alpha Active', 'Zeta Active', 'Alpha Inactive', 'Zed Inactive']);
});
