<?php

namespace App\Livewire\Admin;

use App\Actions\CreateVendorAction;
use App\Actions\ResetTemporaryPasswordAction;
use App\Actions\ResumeVendorAction;
use App\Actions\SuspendVendorAction;
use App\Http\Requests\CreateVendorRequest;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class VendorMaster extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showCreateForm = false;

    public string $name = '';

    public string $email = '';

    public string $company_name = '';

    public string $contact_person = '';

    public string $phone = '';

    public string $notify_email = '';

    public ?string $revealedPassword = null;

    public ?string $revealedForCompany = null;

    /**
     * @var 'created'|'reset'|null
     */
    public ?string $revealedContext = null;

    public function mount(): void
    {
        $this->authorize('viewAny', VendorProfile::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return (new CreateVendorRequest)->rules();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateForm(): void
    {
        $this->authorize('create', VendorProfile::class);

        $this->reset(['name', 'email', 'company_name', 'contact_person', 'phone', 'notify_email']);
        $this->resetErrorBag();
        $this->showCreateForm = true;
    }

    public function cancelCreateForm(): void
    {
        $this->showCreateForm = false;
    }

    public function createVendor(CreateVendorAction $action): void
    {
        $this->authorize('create', VendorProfile::class);

        $validated = $this->validate();

        $result = $action->execute(
            $validated['name'],
            $validated['email'],
            $validated['company_name'],
            $validated['contact_person'],
            $validated['phone'],
            $validated['notify_email'],
        );

        $this->showCreateForm = false;
        $this->revealedPassword = $result['temporary_password'];
        $this->revealedForCompany = $result['vendor_profile']->company_name;
        $this->revealedContext = 'created';
    }

    public function suspend(VendorProfile $vendorProfile, SuspendVendorAction $action): void
    {
        $this->authorize('update', $vendorProfile);

        $action->execute($vendorProfile);
    }

    public function resume(VendorProfile $vendorProfile, ResumeVendorAction $action): void
    {
        $this->authorize('update', $vendorProfile);

        $action->execute($vendorProfile);
    }

    public function resetPassword(VendorProfile $vendorProfile, ResetTemporaryPasswordAction $action): void
    {
        $this->authorize('update', $vendorProfile);

        /** @var User $user */
        $user = $vendorProfile->user;

        $result = $action->execute($user);

        $this->revealedPassword = $result['temporary_password'];
        $this->revealedForCompany = $vendorProfile->company_name;
        $this->revealedContext = 'reset';
    }

    public function dismissReveal(): void
    {
        $this->revealedPassword = null;
        $this->revealedForCompany = null;
        $this->revealedContext = null;
    }

    /**
     * @return LengthAwarePaginator<int, VendorProfile>
     */
    protected function vendors(): LengthAwarePaginator
    {
        return VendorProfile::query()
            ->with('user')
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('company_name', 'like', "%{$this->search}%")
                        ->orWhere('contact_person', 'like', "%{$this->search}%")
                        ->orWhereHas('user', function ($query) {
                            $query->where('name', 'like', "%{$this->search}%")
                                ->orWhere('email', 'like', "%{$this->search}%");
                        });
                });
            })
            ->latest()
            ->paginate(10);
    }

    public function render(): View
    {
        return view('livewire.admin.vendor-master', [
            'vendors' => $this->vendors(),
        ])->title(__('admin.vendor_master.title'));
    }
}
