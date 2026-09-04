<?php

namespace App\Livewire\Admin;

use App\Models\Country;
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

    public function render(): View
    {
        return view('livewire.admin.settings', [
            'countries' => $this->countries(),
        ])->title(__('admin.settings.title'));
    }
}
