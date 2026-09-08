<?php

namespace App\Livewire\Buyer;

use App\Actions\SubmitPartRequestAction;
use App\Enums\PartType;
use App\Http\Requests\SubmitPartRequestRequest;
use App\Models\BuyerProfile;
use App\Models\Maker;
use App\Models\PartRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class RequestForm extends Component
{
    public string $maker_id = '';

    public string $car_model = '';

    public string $vin = '';

    public string $oem_part_number = '';

    public string $part_name = '';

    public string $reference_url = '';

    public string $memo = '';

    /**
     * Why the form is hidden in favour of a "what's next" message, instead
     * of a bare 403 -- CLAUDE.md's UX guidance: blocked states must explain
     * why and what to do, not just deny. Computed once in mount(); a
     * Gate::allows() call alone can't tell us which of the two `act`
     * conditions failed.
     *
     * @var 'unverified'|'unapproved'|null
     */
    public ?string $blockedReason = null;

    /**
     * Confirmation shown inline after a successful submit -- not a
     * session-flash banner, since nothing navigates away afterward (the
     * buyer stays on this page, possibly to submit another). A flash set
     * here would sit in the session unseen: Livewire only re-renders this
     * component's own HTML, never the surrounding layout that reads
     * session('status'), so that banner never gets a request to show it
     * on. Same reasoning as Settings::$justSaved.
     */
    public ?string $submittedCode = null;

    /**
     * Clears the confirmation as soon as the buyer starts a new request --
     * it should only ever describe the request that was just submitted.
     * Only fires for wire:model-driven updates, so this never fights with
     * submit()'s own assignment.
     */
    public function updated(string $property): void
    {
        $this->submittedCode = null;
    }

    public function mount(): void
    {
        $this->authorize('create', PartRequest::class);

        /** @var User $user */
        $user = auth()->user();

        $this->blockedReason = match (true) {
            ! $user->hasVerifiedEmail() => 'unverified',
            ! $user->buyerProfile?->isApproved() => 'unapproved',
            default => null,
        };
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return (new SubmitPartRequestRequest)->rules();
    }

    public function submit(SubmitPartRequestAction $action): void
    {
        // Belt-and-suspenders (CONVENTIONS.md): the form is already hidden
        // behind $blockedReason, but the action that actually creates a
        // request must never rely on the UI alone to enforce this.
        $this->authorize('act');

        $validated = $this->validate();

        /** @var User $user */
        $user = auth()->user();

        /** @var BuyerProfile $buyerProfile */
        $buyerProfile = $user->buyerProfile;

        $request = $action->execute(
            $buyerProfile,
            // The client only deals in new parts (client revision) -- the
            // buyer no longer picks this, and part_type is always New.
            // The column/enum stay as they are (CLAUDE.md: keep intact for
            // future flexibility), only the buyer-facing choice is gone.
            PartType::New,
            (int) $validated['maker_id'],
            $validated['car_model'],
            $validated['vin'],
            $validated['oem_part_number'] ?: null,
            $validated['part_name'],
            $validated['reference_url'] ?: null,
            $validated['memo'] ?: null,
        );

        $this->reset([
            'maker_id', 'car_model', 'vin',
            'oem_part_number', 'part_name', 'reference_url', 'memo',
        ]);

        $this->submittedCode = $request->request_code;
        $this->dispatch('toast', message: __('buyer.request_form.submitted', ['code' => $request->request_code]), type: 'success');
    }

    /**
     * @return Collection<int, Maker>
     */
    protected function activeMakers(): Collection
    {
        return Maker::query()->active()->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.buyer.request-form', [
            'activeMakers' => $this->activeMakers(),
        ])->title(__('buyer.request_form.title'));
    }
}
