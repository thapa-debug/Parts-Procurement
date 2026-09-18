<?php

namespace App\Livewire\Admin;

use App\Models\Country;
use App\Models\Maker;
use App\Models\Setting;
use App\Models\ShippingWeightBracket;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Settings extends Component
{
    public int $margin_rate;

    public int $margin_min_fee;

    public string $admin_sender_email;

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
            'shipping_brackets' => __('admin.settings.nav.shipping_brackets'),
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

    /**
     * Shipping weight brackets (CLAUDE.md §14 Phase 4, rule-based shipping
     * v1): same immediately-applied-actions shape as countries/makers
     * above, reusing SettingPolicy rather than a dedicated policy. Unlike
     * countries/makers, a bracket is never soft-deactivated -- nothing
     * references one by id (ShippingCalculator reads the table fresh every
     * time and PresentQuoteAction only ever copies the resulting fee, not
     * a bracket id, onto presented_quotes), so a real delete is safe.
     *
     * upper_kg is entered blank for the top/catch-all "and above" bracket
     * -- at most one may exist at a time, enforced in addBracket()/
     * saveBracket(), not by a DB constraint.
     */
    public string $new_bracket_upper_kg = '';

    public string $new_bracket_fee = '';

    public ?int $editingBracketId = null;

    public string $editing_bracket_upper_kg = '';

    public string $editing_bracket_fee = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Setting::class);

        $this->margin_rate = (int) Setting::get('margin_rate', 20);
        $this->margin_min_fee = (int) Setting::get('margin_min_fee', 2000);
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
            'admin_sender_email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function save(): void
    {
        $this->authorize('update', Setting::class);

        $validated = $this->validate();

        Setting::set('margin_rate', $validated['margin_rate'], 'integer');
        Setting::set('margin_min_fee', $validated['margin_min_fee'], 'integer');
        Setting::set('admin_sender_email', $validated['admin_sender_email'], 'string');

        $this->dispatch('toast', message: __('admin.settings.saved'), type: 'success');
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

    public function addBracket(): void
    {
        $this->authorize('update', Setting::class);

        $validated = $this->validate([
            'new_bracket_upper_kg' => ['nullable', 'numeric', 'min:0.01', Rule::unique('shipping_weight_brackets', 'upper_kg')],
            'new_bracket_fee' => ['required', 'integer', 'min:0'],
        ], [], [
            'new_bracket_upper_kg' => __('admin.settings.bracket_upper_kg_label'),
        ]);

        $upperKg = $validated['new_bracket_upper_kg'] !== '' ? $validated['new_bracket_upper_kg'] : null;

        if ($upperKg === null && $this->hasCatchAllBracket()) {
            $this->addError('new_bracket_upper_kg', __('admin.settings.bracket_catch_all_exists'));

            return;
        }

        ShippingWeightBracket::create(['upper_kg' => $upperKg, 'fee' => $validated['new_bracket_fee'], 'order' => 0]);
        $this->renormalizeBracketOrder();

        $this->reset('new_bracket_upper_kg', 'new_bracket_fee');
        $this->resetErrorBag(['new_bracket_upper_kg', 'new_bracket_fee']);
    }

    public function startEditingBracket(int $bracketId): void
    {
        $this->authorize('update', Setting::class);

        $bracket = ShippingWeightBracket::findOrFail($bracketId);

        $this->editingBracketId = $bracketId;
        $this->editing_bracket_upper_kg = $bracket->upper_kg === null ? '' : (string) $bracket->upper_kg;
        $this->editing_bracket_fee = (string) $bracket->fee;
    }

    public function cancelEditingBracket(): void
    {
        $this->editingBracketId = null;
        $this->editing_bracket_upper_kg = '';
        $this->editing_bracket_fee = '';
        $this->resetErrorBag(['editing_bracket_upper_kg', 'editing_bracket_fee']);
    }

    public function saveBracket(): void
    {
        $this->authorize('update', Setting::class);

        $validated = $this->validate([
            'editing_bracket_upper_kg' => [
                'nullable', 'numeric', 'min:0.01',
                Rule::unique('shipping_weight_brackets', 'upper_kg')->ignore($this->editingBracketId),
            ],
            'editing_bracket_fee' => ['required', 'integer', 'min:0'],
        ], [], [
            'editing_bracket_upper_kg' => __('admin.settings.bracket_upper_kg_label'),
        ]);

        $upperKg = $validated['editing_bracket_upper_kg'] !== '' ? $validated['editing_bracket_upper_kg'] : null;

        if ($upperKg === null && $this->hasCatchAllBracket($this->editingBracketId)) {
            $this->addError('editing_bracket_upper_kg', __('admin.settings.bracket_catch_all_exists'));

            return;
        }

        ShippingWeightBracket::findOrFail($this->editingBracketId)->update([
            'upper_kg' => $upperKg,
            'fee' => $validated['editing_bracket_fee'],
        ]);
        $this->renormalizeBracketOrder();

        $this->editingBracketId = null;
        $this->editing_bracket_upper_kg = '';
        $this->editing_bracket_fee = '';
    }

    public function deleteBracket(int $bracketId): void
    {
        $this->authorize('update', Setting::class);

        ShippingWeightBracket::findOrFail($bracketId)->delete();
        $this->renormalizeBracketOrder();
    }

    /**
     * Whether a catch-all (null upper_kg) bracket already exists, other
     * than the one currently being edited (if any) -- at most one may
     * exist at a time.
     */
    protected function hasCatchAllBracket(?int $exceptId = null): bool
    {
        return ShippingWeightBracket::query()
            ->whereNull('upper_kg')
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    /**
     * Rewrites every bracket's `order` to match ascending upper_kg (the
     * catch-all, null upper_kg, always sorts last) -- the admin only ever
     * enters a weight cutoff and a fee, never a raw order number, so this
     * keeps `order` correct regardless of what sequence brackets were
     * added/edited/deleted in.
     */
    protected function renormalizeBracketOrder(): void
    {
        ShippingWeightBracket::query()
            ->orderByRaw('upper_kg IS NULL, upper_kg ASC')
            ->get()
            ->values()
            ->each(fn (ShippingWeightBracket $bracket, int $index) => $bracket->update(['order' => $index + 1]));
    }

    /**
     * Ascending by weight, catch-all last -- same ordering
     * renormalizeBracketOrder() itself maintains in the `order` column.
     *
     * @return Collection<int, ShippingWeightBracket>
     */
    protected function brackets(): Collection
    {
        return ShippingWeightBracket::query()->orderBy('order')->get();
    }

    public function render(): View
    {
        return view('livewire.admin.settings', [
            'countries' => $this->countries(),
            'makers' => $this->makers(),
            'brackets' => $this->brackets(),
        ])->title(__('admin.settings.title'));
    }
}
