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
     * Same "shown once, never cleared" reasoning as $sentToCount -- the
     * compare-and-present section disappears in its place once the request
     * moves to `quoted`.
     */
    public ?int $justPresentedBuyerPrice = null;

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
            $this->addError('selectedVendorIds', __('admin.request_detail.select_at_least_one'));

            return;
        }

        try {
            $this->partRequest = $action->execute($this->partRequest, $this->selectedVendorIds);
        } catch (RequestCannotBeBroadcastException $e) {
            report($e);
            $this->addError('selectedVendorIds', __('admin.request_detail.broadcast_error'));

            return;
        }

        $this->sentToCount = $this->partRequest->vendors()->count();
    }

    public function presentQuote(int $vendorResponseId, PresentQuoteAction $action): void
    {
        $this->authorize('presentQuote', $this->partRequest);

        $vendorResponse = VendorResponse::findOrFail($vendorResponseId);

        try {
            $this->partRequest = $action->execute($this->partRequest, $vendorResponse);
        } catch (PresentQuoteNotAllowedException $e) {
            report($e);
            $this->addError('presentQuote', __('admin.request_detail.present_quote_error'));

            return;
        }

        $this->justPresentedBuyerPrice = $this->partRequest->buyer_price;
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
        ])->title($this->partRequest->request_code);
    }
}
