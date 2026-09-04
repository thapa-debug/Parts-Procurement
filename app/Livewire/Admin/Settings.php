<?php

namespace App\Livewire\Admin;

use App\Models\Country;
use App\Models\Maker;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Settings extends Component
{
    public int $margin_rate;

    public int $margin_min_fee;

    /**
     * Vehicle/container are provisional fixed fees pending client
     * confirmation. DHL is deliberately NOT here -- it's per-request
     * (admin-entered at quote time, part_requests.shipping_fee), not a
     * single global number. See CONVENTIONS.md "Shipping fees are
     * provisional" and CLAUDE.md §14 Phase 4.
     */
    public int $shipping_fee_vehicle;

    public int $shipping_fee_container;

    public string $admin_sender_email;

    public bool $justSaved = false;

    /**
     * Which section the left nav currently has selected -- purely a view
     * concern (CLAUDE.md §8: no business logic in the component beyond
     * this). Every section's underlying fields/methods stay exactly as
     * they were before this nav existed; only one section's markup is
     * ever rendered at a time.
     */
    public string $activeSection = 'general';

    /**
     * @return array<string, string>
     */
    public static function sections(): array
    {
        return [
            'general' => __('admin.settings.nav.general'),
            'countries' => __('admin.settings.nav.countries'),
            'makers' => __('admin.settings.nav.makers'),
        ];
    }

    public function showSection(string $section): void
    {
        $this->activeSection = array_key_exists($section, static::sections()) ? $section : 'general';
    }

    /**
     * Country management (client revision): a small CRUD list embedded in
     * this same page rather than a separate route, gated by the same
     * SettingPolicy::update check every other action here already uses --
     * country management is a settings capability, not a distinct
     * resource, so a dedicated CountryPolicy would just be redundant
     * indirection. Kept out of the main $margin_rate-style "one big form,
     * one save button" shape: add/edit/activate/deactivate are independent,
     * immediately-applied actions on a list, the same shape as
     * VendorMaster's suspend/resume, not a batch of fields saved together.
     */
    public string $new_country_name = '';

    public ?int $editingCountryId = null;

    public string $editing_country_name = '';

    /**
     * Filters the country list below, the same live-search pattern used by
     * BuyerMaster/VendorMaster -- kept separate from those since it filters
     * a different, non-paginated collection scoped to this page.
     */
    public string $countrySearch = '';

    /**
     * Maker management (client revision): exact mirror of the country
     * section above -- same reasoning for reusing SettingPolicy instead of
     * a dedicated MakerPolicy, same immediately-applied-actions shape.
     */
    public string $new_maker_name = '';

    public ?int $editingMakerId = null;

    public string $editing_maker_name = '';

    public string $makerSearch = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Setting::class);

        $this->margin_rate = (int) Setting::get('margin_rate', 20);
        $this->margin_min_fee = (int) Setting::get('margin_min_fee', 2000);
        $this->shipping_fee_vehicle = (int) Setting::get('shipping_fee_vehicle', 0);
        $this->shipping_fee_container = (int) Setting::get('shipping_fee_container', 0);
        $this->admin_sender_email = (string) Setting::get('admin_sender_email', '');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'margin_rate' => ['required', 'integer', 'min:0'],
            'margin_min_fee' => ['required', 'integer', 'min:0'],
            'shipping_fee_vehicle' => ['required', 'integer', 'min:0'],
            'shipping_fee_container' => ['required', 'integer', 'min:0'],
            'admin_sender_email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Clears the "Saved" indicator as soon as the admin changes any field --
     * it should only ever describe the values currently on screen. Only
     * fires for wire:model-driven updates, so this never fights with the
     * `save()` method's own `justSaved = true` assignment.
     */
    public function updated(string $property): void
    {
        $this->justSaved = false;
    }

    public function save(): void
    {
        $this->authorize('update', Setting::class);

        $validated = $this->validate();

        Setting::set('margin_rate', $validated['margin_rate'], 'integer');
        Setting::set('margin_min_fee', $validated['margin_min_fee'], 'integer');
        Setting::set('shipping_fee_vehicle', $validated['shipping_fee_vehicle'], 'integer');
        Setting::set('shipping_fee_container', $validated['shipping_fee_container'], 'integer');
        Setting::set('admin_sender_email', $validated['admin_sender_email'], 'string');

        $this->justSaved = true;
    }

    public function addCountry(): void
    {
        $this->authorize('update', Setting::class);

        $validated = $this->validate([
            'new_country_name' => ['required', 'string', 'max:255', 'unique:countries,name'],
        ]);

        Country::create(['name' => $validated['new_country_name'], 'is_active' => true]);

        $this->reset('new_country_name');
        $this->resetErrorBag('new_country_name');
    }

    public function startEditingCountry(int $countryId): void
    {
        $this->authorize('update', Setting::class);

        $country = Country::findOrFail($countryId);

        $this->editingCountryId = $countryId;
        $this->editing_country_name = $country->name;
    }

    public function cancelEditingCountry(): void
    {
        $this->editingCountryId = null;
        $this->editing_country_name = '';
        $this->resetErrorBag('editing_country_name');
    }

    public function saveCountry(): void
    {
        $this->authorize('update', Setting::class);

        $validated = $this->validate([
            'editing_country_name' => [
                'required', 'string', 'max:255',
                Rule::unique('countries', 'name')->ignore($this->editingCountryId),
            ],
        ]);

        Country::findOrFail($this->editingCountryId)->update(['name' => $validated['editing_country_name']]);

        $this->editingCountryId = null;
        $this->editing_country_name = '';
    }

    public function toggleCountryActive(int $countryId): void
    {
        $this->authorize('update', Setting::class);

        $country = Country::findOrFail($countryId);
        $country->update(['is_active' => ! $country->is_active]);
    }

    /**
     * Active countries first (the ones an admin is most likely working
     * with), then inactive, alphabetical within each group.
     *
     * @return Collection<int, Country>
     */
    protected function countries(): Collection
    {
        return Country::query()
            ->when($this->countrySearch, fn ($query) => $query->where('name', 'like', "%{$this->countrySearch}%"))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function addMaker(): void
    {
        $this->authorize('update', Setting::class);

        $validated = $this->validate([
            'new_maker_name' => ['required', 'string', 'max:255', 'unique:makers,name'],
        ]);

        Maker::create(['name' => $validated['new_maker_name'], 'is_active' => true]);

        $this->reset('new_maker_name');
        $this->resetErrorBag('new_maker_name');
    }

    public function startEditingMaker(int $makerId): void
    {
        $this->authorize('update', Setting::class);

        $maker = Maker::findOrFail($makerId);

        $this->editingMakerId = $makerId;
        $this->editing_maker_name = $maker->name;
    }

    public function cancelEditingMaker(): void
    {
        $this->editingMakerId = null;
        $this->editing_maker_name = '';
        $this->resetErrorBag('editing_maker_name');
    }

    public function saveMaker(): void
    {
        $this->authorize('update', Setting::class);

        $validated = $this->validate([
            'editing_maker_name' => [
                'required', 'string', 'max:255',
                Rule::unique('makers', 'name')->ignore($this->editingMakerId),
            ],
        ]);

        Maker::findOrFail($this->editingMakerId)->update(['name' => $validated['editing_maker_name']]);

        $this->editingMakerId = null;
        $this->editing_maker_name = '';
    }

    public function toggleMakerActive(int $makerId): void
    {
        $this->authorize('update', Setting::class);

        $maker = Maker::findOrFail($makerId);
        $maker->update(['is_active' => ! $maker->is_active]);
    }

    /**
     * Active makers first, then inactive, alphabetical within each group --
     * same ordering as countries() above.
     *
     * @return Collection<int, Maker>
     */
    protected function makers(): Collection
    {
        return Maker::query()
            ->when($this->makerSearch, fn ($query) => $query->where('name', 'like', "%{$this->makerSearch}%"))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.admin.settings', [
            'countries' => $this->countries(),
            'makers' => $this->makers(),
        ])->title(__('admin.settings.title'));
    }
}
