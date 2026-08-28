<?php

namespace App\Livewire\Admin;

use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class BuyerDetail extends Component
{
    public BuyerProfile $buyerProfile;

    public string $company_name = '';

    public string $phone = '';

    public string $default_destination_country = '';

    public string $default_yard = '';

    public bool $justSaved = false;

    public function mount(BuyerProfile $buyerProfile): void
    {
        // Gated on 'update', not 'view' -- see VendorDetail for why.
        $this->authorize('update', $buyerProfile);

        $this->buyerProfile = $buyerProfile;
        $this->company_name = $buyerProfile->company_name;
        $this->phone = $buyerProfile->phone;
        $this->default_destination_country = $buyerProfile->default_destination_country;
        $this->default_yard = $buyerProfile->default_yard;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'default_destination_country' => ['required', 'string', 'max:255'],
            'default_yard' => ['required', 'string', 'max:255'],
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

        $this->buyerProfile->update($validated);

        $this->justSaved = true;
    }

    public function render(): View
    {
        /** @var User $user */
        $user = $this->buyerProfile->user;

        return view('livewire.admin.buyer-detail', [
            'fields' => [
                ['name' => 'company_name', 'label' => __('admin.buyer_master.create_form.company_name_label'), 'required' => true, 'placeholder' => __('admin.buyer_master.create_form.company_name_placeholder')],
                ['name' => 'phone', 'label' => __('admin.buyer_master.create_form.phone_label'), 'required' => true, 'placeholder' => __('admin.buyer_master.create_form.phone_placeholder')],
                ['name' => 'default_destination_country', 'label' => __('admin.buyer_master.create_form.default_destination_country_label'), 'required' => true, 'placeholder' => __('admin.buyer_master.create_form.default_destination_country_placeholder')],
                ['name' => 'default_yard', 'label' => __('admin.buyer_master.create_form.default_yard_label'), 'required' => true, 'placeholder' => __('admin.buyer_master.create_form.default_yard_placeholder')],
            ],
            'accountFields' => [
                ['label' => __('admin.profile_edit.name_label'), 'value' => $user->name],
                ['label' => __('admin.profile_edit.email_label'), 'value' => $user->email],
                ['label' => __('admin.buyer_master.table.member_code'), 'value' => $this->buyerProfile->member_code],
            ],
        ])->title($this->buyerProfile->company_name);
    }
}
