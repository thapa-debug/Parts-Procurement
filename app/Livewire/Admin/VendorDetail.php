<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class VendorDetail extends Component
{
    public VendorProfile $vendorProfile;

    public string $company_name = '';

    public string $contact_person = '';

    public string $phone = '';

    public string $notify_email = '';

    public bool $justSaved = false;

    public function mount(VendorProfile $vendorProfile): void
    {
        // Gated on 'update', not 'view' -- this page's whole purpose is
        // editing, and VendorProfilePolicy::view also allows a vendor to
        // view their own profile (for a future self-service page), which
        // is not who this admin-only screen is for.
        $this->authorize('update', $vendorProfile);

        $this->vendorProfile = $vendorProfile;
        $this->company_name = $vendorProfile->company_name;
        $this->contact_person = $vendorProfile->contact_person;
        $this->phone = $vendorProfile->phone;
        $this->notify_email = $vendorProfile->notify_email;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'notify_email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function updated(string $property): void
    {
        $this->justSaved = false;
    }

    public function save(): void
    {
        $this->authorize('update', $this->vendorProfile);

        $validated = $this->validate();

        $this->vendorProfile->update($validated);

        $this->justSaved = true;
    }

    public function render(): View
    {
        /** @var User $user */
        $user = $this->vendorProfile->user;

        return view('livewire.admin.vendor-detail', [
            'fields' => [
                ['name' => 'company_name', 'label' => __('admin.vendor_master.create_form.company_name_label'), 'required' => true, 'placeholder' => __('admin.vendor_master.create_form.company_name_placeholder')],
                ['name' => 'contact_person', 'label' => __('admin.vendor_master.create_form.contact_person_label'), 'required' => true, 'placeholder' => __('admin.vendor_master.create_form.contact_person_placeholder'), 'help' => __('admin.vendor_master.create_form.contact_person_help')],
                ['name' => 'phone', 'label' => __('admin.vendor_master.create_form.phone_label'), 'required' => true, 'placeholder' => __('admin.vendor_master.create_form.phone_placeholder')],
                ['name' => 'notify_email', 'label' => __('admin.vendor_master.create_form.notify_email_label'), 'type' => 'email', 'required' => true, 'placeholder' => __('admin.vendor_master.create_form.notify_email_placeholder'), 'help' => __('admin.vendor_master.create_form.notify_email_help')],
            ],
            'accountFields' => [
                ['label' => __('admin.profile_edit.name_label'), 'value' => $user->name],
                ['label' => __('admin.profile_edit.email_label'), 'value' => $user->email],
            ],
        ])->title($this->vendorProfile->company_name);
    }
}
