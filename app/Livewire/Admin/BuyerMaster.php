<?php

namespace App\Livewire\Admin;

use App\Actions\CreateBuyerAction;
use App\Actions\ResetTemporaryPasswordAction;
use App\Http\Requests\CreateBuyerRequest;
use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Mirrors VendorMaster's shape exactly, minus suspend/resume -- buyer_profiles
 * has no status column (CLAUDE.md §7), so there's nothing to toggle.
 */
class BuyerMaster extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showCreateForm = false;

    public string $name = '';

    public string $email = '';

    public string $company_name = '';

    public string $default_destination_country = '';

    public string $default_yard = '';

    public string $phone = '';

    /**
     * Defaults to checked -- "activate now" is the common case for a buyer
     * the admin is deliberately creating (CLAUDE.md §14).
     */
    public bool $approve_immediately = true;

    public ?string $revealedPassword = null;

    public ?string $revealedForCompany = null;

    /**
     * @var 'created'|'reset'|null
     */
    public ?string $revealedContext = null;

    /**
     * Set only alongside a fresh creation, so the reveal modal can tell the
     * admin a verification email already went out. Null on a password
     * reset -- that doesn't touch verification state.
     */
    public ?string $revealedVerificationEmail = null;

    public function mount(): void
    {
        $this->authorize('viewAny', BuyerProfile::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return (new CreateBuyerRequest)->rules();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreateForm(): void
    {
        $this->authorize('create', BuyerProfile::class);

        $this->reset(['name', 'email', 'company_name', 'default_destination_country', 'default_yard', 'phone']);
        $this->approve_immediately = true;
        $this->resetErrorBag();
        $this->showCreateForm = true;
    }

    public function cancelCreateForm(): void
    {
        $this->showCreateForm = false;
    }

    public function createBuyer(CreateBuyerAction $action): void
    {
        $this->authorize('create', BuyerProfile::class);

        $validated = $this->validate();

        /** @var User $admin */
        $admin = auth()->user();

        $result = $action->execute(
            $validated['name'],
            $validated['email'],
            $validated['company_name'],
            $validated['default_destination_country'],
            $validated['default_yard'],
            $validated['phone'],
            $admin,
            $validated['approve_immediately'],
        );

        $this->showCreateForm = false;
        $this->revealedPassword = $result['temporary_password'];
        $this->revealedForCompany = $result['buyer_profile']->company_name;
        $this->revealedContext = 'created';
        $this->revealedVerificationEmail = $result['user']->email;
    }

    public function resetPassword(BuyerProfile $buyerProfile, ResetTemporaryPasswordAction $action): void
    {
        $this->authorize('update', $buyerProfile);

        /** @var User $user */
        $user = $buyerProfile->user;

        $result = $action->execute($user);

        $this->revealedPassword = $result['temporary_password'];
        $this->revealedForCompany = $buyerProfile->company_name;
        $this->revealedContext = 'reset';
        $this->revealedVerificationEmail = null;
    }

    public function dismissReveal(): void
    {
        $this->revealedPassword = null;
        $this->revealedForCompany = null;
        $this->revealedContext = null;
        $this->revealedVerificationEmail = null;
    }

    /**
     * See VendorMaster::resendVerification() for why this doesn't reuse the
     * verification.send route.
     */
    public function resendVerification(BuyerProfile $buyerProfile): void
    {
        $this->authorize('update', $buyerProfile);

        /** @var User $user */
        $user = $buyerProfile->user;

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
    }

    /**
     * @return LengthAwarePaginator<int, BuyerProfile>
     */
    protected function buyers(): LengthAwarePaginator
    {
        return BuyerProfile::query()
            ->with('user')
            ->when($this->search, function ($query) {
                $query->where(function ($query) {
                    $query->where('company_name', 'like', "%{$this->search}%")
                        ->orWhere('member_code', 'like', "%{$this->search}%")
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
        return view('livewire.admin.buyer-master', [
            'buyers' => $this->buyers(),
        ])->title(__('admin.buyer_master.title'));
    }
}
