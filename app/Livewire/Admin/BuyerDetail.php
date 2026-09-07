<?php

namespace App\Livewire\Admin;

use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class BuyerDetail extends Component
{
    public BuyerProfile $buyerProfile;

    public string $company_name = '';

    public string $phone = '';

    public string $country_id = '';

    public bool $justSaved = false;

    public function mount(BuyerProfile $buyerProfile): void
    {
        // Gated on 'update', not 'view' -- see VendorDetail for why.
        $this->authorize('update', $buyerProfile);

        $this->buyerProfile = $buyerProfile;
        $this->company_name = $buyerProfile->company_name;
        $this->phone = $buyerProfile->phone;
        $this->country_id = (string) $buyerProfile->country_id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            // Not restricted to active countries, unlike CreateBuyerRequest:
            // this buyer may already be assigned a country the admin has
            // since deactivated, and resubmitting the form unchanged (e.g.
            // to save a phone-number edit) must not fail just because that
            // selection is no longer offered for *new* picks.
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')],
        ];
    }

    public function updated(string $property): void
    {
        $this->justSaved = false;
    }

    public function save(): void
    {
        $this->authorize('update', $this->buyerProfile);

        $validated = $this->validate();
        $validated['country_id'] = (int) $validated['country_id'];

        $this->buyerProfile->update($validated);

        $this->justSaved = true;
    }

    public function render(): View
    {
        /** @var User $user */
        $user = $this->buyerProfile->user;

        // Active countries, plus this buyer's own current country even if
        // it's since been deactivated -- otherwise the <select> would have
        // no matching <option> for their real, unchanged value and render
        // as if nothing were selected, misleading the admin into thinking
        // it needs to be re-picked.
        $countryOptions = Country::query()
            ->where('is_active', true)
            ->orWhere('id', $this->buyerProfile->country_id)
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('livewire.admin.buyer-detail', [
            'fields' => [
                ['name' => 'company_name', 'label' => __('admin.buyer_master.create_form.company_name_label'), 'required' => true, 'placeholder' => __('admin.buyer_master.create_form.company_name_placeholder')],
                ['name' => 'phone', 'label' => __('admin.buyer_master.create_form.phone_label'), 'required' => true, 'placeholder' => __('admin.buyer_master.create_form.phone_placeholder')],
                ['name' => 'country_id', 'label' => __('admin.buyer_master.create_form.country_label'), 'required' => true, 'type' => 'select', 'options' => $countryOptions, 'placeholderOption' => __('admin.buyer_master.create_form.country_placeholder_option')],
            ],
            'accountFields' => [
                ['label' => __('admin.profile_edit.name_label'), 'value' => $user->name],
                ['label' => __('admin.profile_edit.email_label'), 'value' => $user->email],
                ['label' => __('admin.buyer_master.table.member_code'), 'value' => $this->buyerProfile->member_code],
            ],
        ])->title($this->buyerProfile->company_name);
    }
}
