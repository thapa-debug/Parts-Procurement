<?php

namespace App\Livewire\Admin;

use App\Actions\BroadcastRequestAction;
use App\Actions\PresentQuoteAction;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\VendorStatus;
use App\Exceptions\PresentQuoteNotAllowedException;
use App\Exceptions\RequestCannotBeBroadcastException;
use App\Exceptions\ShippingBracketNotConfiguredException;
use App\Models\PartRequest;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use App\Services\PricingService;
use App\Services\ShippingCalculator;
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
     * Shown once, right after a successful send -- not cleared afterward,
     * since there's nothing else editable on this page once the request
     * moves out of `new`; the vendor broadcast form itself disappears in
     * its place (see render()/the view), which is confirmation enough for
     * the rest of the page's life. A toast fires too (see sendInquiry()),
     * but that's transient -- this is the durable record of it.
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

    /**
     * Keyed by vendor_response id -- the admin's shipping-fee override for
     * that response, if any (CLAUDE.md §14 Phase 4: rule-based shipping
     * v1). Left blank/unset, PresentQuoteAction uses ShippingCalculator's
     * own figure instead. A response with an override MUST also have a
     * non-blank reason in $shippingFeeOverrideReasons -- enforced by
     * overrideRules() below, not left to PresentQuoteAction's own guard
     * alone, so the admin gets a normal per-field validation error rather
     * than the whole batch silently skipping that response.
     *
     * @var array<int, string>
     */
    public array $shippingFeeOverrides = [];

    /**
     * @var array<int, string>
     */
    public array $shippingFeeOverrideReasons = [];

    /**
     * Applies to the whole "present selected" batch, not per-response --
     * CLAUDE.md §14 Phase 4 slice 5 (無償): a request cannot mix free and
     * paid presented quotes, so there is only one free/paid decision to
     * make per click, not one per checked response. Checking this hides
     * the per-response shipping-override fields above (mutually exclusive
     * with $isFree in PresentQuoteAction -- nothing to override on a fee
     * that's forced to ¥0).
     */
    public bool $presentAsFree = false;

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

        $this->validate($this->overrideRules());

        $presentedCount = 0;

        foreach (VendorResponse::query()->find($this->selectedResponseIdsToPresent) as $vendorResponse) {
            $override = $this->presentAsFree ? null : ($this->shippingFeeOverrides[$vendorResponse->id] ?? '');
            $override = $override === '' || $override === null ? null : (int) $override;
            $reason = $override !== null ? ($this->shippingFeeOverrideReasons[$vendorResponse->id] ?? null) : null;

            try {
                $action->execute($this->partRequest, $vendorResponse, $override, $reason, $this->presentAsFree);
                $presentedCount++;
            } catch (PresentQuoteNotAllowedException $e) {
                report($e);
            }
        }

        $this->partRequest = $this->partRequest->fresh();
        $this->reset('selectedResponseIdsToPresent', 'shippingFeeOverrides', 'shippingFeeOverrideReasons', 'presentAsFree');

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
     * A reason is required precisely when that same response has a
     * non-blank override -- built dynamically since these are array-keyed
     * properties, one pair per currently-checked response.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function overrideRules(): array
    {
        $rules = [];

        foreach ($this->selectedResponseIdsToPresent as $responseId) {
            // A free batch ignores whatever these fields hold (see
            // presentSelectedQuotes()) -- no point demanding a reason for
            // an override that will never be applied.
            $hasOverride = ! $this->presentAsFree && filled($this->shippingFeeOverrides[$responseId] ?? null);

            $rules["shippingFeeOverrides.{$responseId}"] = ['nullable', 'integer', 'min:0'];
            $rules["shippingFeeOverrideReasons.{$responseId}"] = [$hasOverride ? 'required' : 'nullable', 'string', 'max:1000'];
        }

        return $rules;
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

        // The same figure PresentQuoteAction would use if the admin
        // doesn't override it -- shown so the admin can decide whether to.
        // A response with no weight recorded (shouldn't happen for a real
        // quote, see PresentQuoteAction's own guard) or an unconfigured
        // bracket table just shows nothing rather than crashing the page.
        $shippingCalculator = app(ShippingCalculator::class);
        $vendorResponseShipping = $vendorResponses
            ->filter(fn (VendorResponse $response) => ! $response->is_no_stock && $response->cost_price !== null && $response->weight_kg !== null)
            ->mapWithKeys(function (VendorResponse $response) use ($shippingCalculator) {
                try {
                    return [$response->id => $shippingCalculator->calculate((float) $response->weight_kg)];
                } catch (ShippingBracketNotConfiguredException) {
                    return [$response->id => null];
                }
            });

        return view('livewire.admin.request-detail', [
            'activeVendors' => $this->activeVendors(),
            'invitedVendors' => $this->partRequest->status !== RequestStatus::New
                ? $this->partRequest->vendors()->orderBy('vendor_profiles.company_name')->get()
                : Collection::make(),
            'vendorResponses' => $vendorResponses,
            'vendorResponsePricing' => $vendorResponsePricing,
            'vendorResponseShipping' => $vendorResponseShipping,
            'presentedResponseIds' => $this->partRequest->presentedQuotes()->pluck('vendor_response_id')->all(),
            // The actual frozen figures for an already-presented response,
            // keyed by vendor_response_id -- vendorResponsePricing/
            // vendorResponseShipping above are always the live "what would
            // this cost right now" preview (used before presenting, to help
            // the admin decide), which can be badly wrong once a response
            // is actually presented as free (CLAUDE.md §14 Phase 4 slice 5:
            // the real buyer_price/shipping_fee are forced to 0, nothing
            // like the preview). The Blade view prefers these over the
            // preview whenever a response is presented.
            'presentedQuotesByResponseId' => $this->partRequest->presentedQuotes()
                ->get(['vendor_response_id', 'buyer_price', 'shipping_fee', 'is_free'])
                ->keyBy('vendor_response_id'),
            // The confirmed payment, if any (CLAUDE.md §6.3 gate) -- admin
            // has had no visibility into this at all until now, even though
            // confirming a vendor purchase depends entirely on it existing.
            'confirmedPayment' => $this->partRequest->hasBeenPaid()
                ? $this->partRequest->payments()->where('status', PaymentStatus::Confirmed)->latest('paid_at')->first()
                : null,
        ])->title($this->partRequest->request_code);
    }
}
