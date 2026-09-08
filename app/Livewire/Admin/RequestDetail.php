<?php

namespace App\Livewire\Admin;

use App\Actions\BroadcastRequestAction;
use App\Actions\PresentQuoteAction;
use App\Enums\RequestStatus;
use App\Enums\VendorStatus;
use App\Exceptions\PresentQuoteNotAllowedException;
use App\Exceptions\RequestCannotBeBroadcastException;
use App\Models\PartRequest;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use App\Services\PricingService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class RequestDetail extends Component
{
    public PartRequest $partRequest;

    /**
     * @var array<int, int>
     */
    public array $selectedVendorIds = [];

    /**
     * Shown once, right after a successful send -- not cleared afterward
     * (unlike Settings::$justSaved), since there's nothing else editable
     * on this page once the request moves out of `new`; the vendor
     * broadcast form itself disappears in its place (see render()/the
     * view), which is confirmation enough for the rest of the page's life.
     */
    public ?int $sentToCount = null;

    /**
     * Which not-yet-presented responses the admin has checked, staged for
     * one deliberate "Present to buyer" click (client revision -- checking
     * a box used to present it immediately; a buyer could then be notified
     * about a quote the admin un-checked moments later, landing on a
     * confusing blank). Mirrors $selectedVendorIds's own
     * checkboxes-then-one-button shape above.
     *
     * @var array<int, int>
     */
    public array $selectedResponseIdsToPresent = [];

    public function mount(PartRequest $partRequest): void
    {
        // 'viewBoard', not 'view' -- 'view' also permits a buyer to see
        // their own request (for a future self-service page), which is
        // not who this admin-only screen (with its vendor-broadcast
        // controls) is for. Same reasoning as VendorDetail/BuyerDetail
        // gating on 'update' rather than 'view'.
        $this->authorize('viewBoard', PartRequest::class);

        $this->partRequest = $partRequest->load('maker');
        $this->selectedVendorIds = $this->activeVendors()->pluck('id')->all();
    }

    public function selectAllVendors(): void
    {
        $this->selectedVendorIds = $this->activeVendors()->pluck('id')->all();
    }

    public function sendInquiry(BroadcastRequestAction $action): void
    {
        $this->authorize('broadcast', $this->partRequest);

        if ($this->selectedVendorIds === []) {
            $message = __('admin.request_detail.select_at_least_one');
            $this->addError('selectedVendorIds', $message);
            $this->dispatch('toast', message: $message, type: 'error');

            return;
        }

        try {
            $this->partRequest = $action->execute($this->partRequest, $this->selectedVendorIds);
        } catch (RequestCannotBeBroadcastException $e) {
            report($e);
            $message = __('admin.request_detail.broadcast_error');
            $this->addError('selectedVendorIds', $message);
            $this->dispatch('toast', message: $message, type: 'error');

            return;
        }

        $this->sentToCount = $this->partRequest->vendors()->count();
        $this->dispatch('toast', message: __('admin.request_detail.sent_confirmation', ['count' => $this->sentToCount]), type: 'success');
    }

    /**
     * Presents every currently-checked response in one deliberate action,
     * confirmed client-side (wire:confirm in the Blade view) before this
     * ever runs -- client revision: replaces a casual per-checkbox toggle
     * that presented the instant a box was checked. There is deliberately
     * no way to un-present/withdraw a quote once presented (client
     * revision: a buyer could otherwise be notified about a quote that's
     * then silently pulled out from under them) -- presenting is final. A
     * response that fails (already presented, turned no-stock, etc. -- e.g.
     * a second admin tab) is skipped rather than aborting the whole batch;
     * only a total failure surfaces an error.
     */
    public function presentSelectedQuotes(PresentQuoteAction $action): void
    {
        $this->authorize('presentQuote', $this->partRequest);

        if ($this->selectedResponseIdsToPresent === []) {
            $message = __('admin.request_detail.select_at_least_one_quote');
            $this->addError('presentQuote', $message);
            $this->dispatch('toast', message: $message, type: 'error');

            return;
        }

        $presentedCount = 0;

        foreach (VendorResponse::query()->find($this->selectedResponseIdsToPresent) as $vendorResponse) {
            try {
                $action->execute($this->partRequest, $vendorResponse);
                $presentedCount++;
            } catch (PresentQuoteNotAllowedException $e) {
                report($e);
            }
        }

        $this->partRequest = $this->partRequest->fresh();
        $this->reset('selectedResponseIdsToPresent');

        if ($presentedCount === 0) {
            $message = __('admin.request_detail.present_quote_error');
            $this->addError('presentQuote', $message);
            $this->dispatch('toast', message: $message, type: 'error');

            return;
        }

        // Durable feedback is the "Presented" badge (see the Blade view)
        // -- this toast is just a brief, dismissable extra.
        $this->dispatch('toast', message: __('admin.request_detail.presented_toast', ['count' => $presentedCount]), type: 'success');
    }

    /**
     * @return Collection<int, VendorProfile>
     */
    protected function activeVendors(): Collection
    {
        return VendorProfile::query()
            ->where('status', VendorStatus::Active)
            ->orderBy('company_name')
            ->get();
    }

    public function render(): View
    {
        $vendorResponses = $this->partRequest->status !== RequestStatus::New
            ? $this->partRequest->vendorResponses()->with(['vendor', 'photos'])->orderBy('created_at')->get()
            : Collection::make();

        $pricingService = app(PricingService::class);

        /** @var \Illuminate\Support\Collection<int, array{cost_price: int, applied_rate: int, applied_min_fee: int, margin: int, buyer_price: int}> $vendorResponsePricing */
        $vendorResponsePricing = $vendorResponses
            ->filter(fn (VendorResponse $response) => ! $response->is_no_stock && $response->cost_price !== null)
            ->mapWithKeys(fn (VendorResponse $response) => [$response->id => $pricingService->calculate($response->cost_price)]);

        return view('livewire.admin.request-detail', [
            'activeVendors' => $this->activeVendors(),
            'invitedVendors' => $this->partRequest->status !== RequestStatus::New
                ? $this->partRequest->vendors()->orderBy('vendor_profiles.company_name')->get()
                : Collection::make(),
            'vendorResponses' => $vendorResponses,
            'vendorResponsePricing' => $vendorResponsePricing,
            'presentedResponseIds' => $this->partRequest->presentedQuotes()->pluck('vendor_response_id')->all(),
        ])->title($this->partRequest->request_code);
    }
}
