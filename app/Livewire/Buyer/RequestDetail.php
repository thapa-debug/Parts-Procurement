<?php

namespace App\Livewire\Buyer;

use App\Actions\SelectQuoteAction;
use App\Enums\QualityRank;
use App\Exceptions\SelectQuoteNotAllowedException;
use App\Models\PartRequest;
use App\Models\PresentedQuote;
use App\Models\VendorResponse;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class RequestDetail extends Component
{
    /**
     * Only the id is kept as component state -- never the PartRequest model
     * itself. A public Eloquent-model property gets its full attribute set
     * serialized into Livewire's client-side snapshot regardless of what
     * the Blade view renders, which would put cost_price/selected_response_id
     * (an internal FK to the vendor's own response) into the buyer's own
     * browser. render() below re-fetches with an explicit narrow ->select()
     * every time instead -- same shape as App\Livewire\Vendor\RequestResponse.
     */
    public int $partRequestId;

    public function mount(PartRequest $partRequest): void
    {
        // Not view(): that also permits an admin (correctly, for the
        // admin's own screens) -- viewOwn() is buyer-and-owner only, so an
        // admin session can never mount this buyer-only component either,
        // matching the viewBoard-vs-view discipline already established
        // for the admin side.
        $this->authorize('viewOwn', $partRequest);

        $this->partRequestId = $partRequest->id;
    }

    /**
     * The buyer picking (or re-picking) one of their own request's
     * presented quotes (client revision: replaces Phase 2's one-quote,
     * no-choice model). $partRequest is re-fetched here rather than kept as
     * component state -- see the class docblock -- so authorize() always
     * checks the real, current row.
     */
    public function selectQuote(int $presentedQuoteId, SelectQuoteAction $action): void
    {
        $partRequest = PartRequest::findOrFail($this->partRequestId);
        $this->authorize('selectQuote', $partRequest);

        $presentedQuote = PresentedQuote::findOrFail($presentedQuoteId);

        try {
            $action->execute($partRequest, $presentedQuote);
        } catch (SelectQuoteNotAllowedException $e) {
            report($e);
            $this->addError('selectQuote', __('buyer.request_detail.select_quote_error'));
        }
    }

    public function render(): View
    {
        // Fetched separately from the main query below, and never selected
        // onto the $partRequest passed to the view: a raw internal FK to a
        // vendor_response row has no reason to ever reach the buyer's
        // browser, even though it isn't "vendor identity" by itself.
        $selectedResponseId = PartRequest::query()->where('id', $this->partRequestId)->value('selected_response_id');

        // Explicit column allowlist -- cost_price, applied_rate,
        // applied_min_fee, and selected_response_id are deliberately absent.
        // buyer_price is the one price column a buyer is ever allowed to
        // see. shipping_* columns are the buyer's own snapshot (CLAUDE.md
        // §14 Phase 4 slice 2/3) -- safe to show once set, which only
        // happens once the request has actually been paid for.
        $partRequest = PartRequest::query()
            ->select([
                'id', 'request_code', 'part_type', 'maker_id', 'car_model', 'vin',
                'oem_part_number', 'part_name', 'reference_url', 'memo',
                'status', 'buyer_price', 'created_at', 'is_free',
                'shipping_method', 'shipping_fee', 'shipping_recipient_name',
                'shipping_phone', 'shipping_postal_code', 'shipping_country',
                'shipping_state', 'shipping_city', 'shipping_address_line1',
                'shipping_address_line2',
            ])
            ->with('maker')
            ->findOrFail($this->partRequestId);

        $options = $this->presentedQuoteOptions($selectedResponseId);

        return view('livewire.buyer.request-detail', [
            'partRequest' => $partRequest,
            'options' => $options,
        ])->title($partRequest->request_code);
    }

    /**
     * Every vendor response currently presented as an option to choose
     * from (client revision: multiple quotes may be presented at once,
     * replacing Phase 2's single $quote lookup). Built as a plain array per
     * option, never a model -- so there is no property a future Blade edit
     * could accidentally reach for ->vendor or ->cost_price through, and
     * nothing here ever becomes public component state (never serialized
     * into Livewire's client-side snapshot). Only what CLAUDE.md §4 allows
     * a buyer to see per option: its own id (to select it), the photo(s),
     * the quality rank, its own snapshotted buyer_price -- never
     * recomputed from the vendor's cost -- and whether it's the buyer's
     * current pick (a plain boolean, never the raw selected_response_id
     * this is compared against).
     *
     * @return array<int, array{presented_quote_id: int, buyer_price: int, quality_rank: QualityRank, photos: array<int, string>, is_selected: bool, is_free: bool}>
     */
    protected function presentedQuoteOptions(?int $selectedResponseId): array
    {
        $presentedQuotes = PresentedQuote::query()
            ->where('part_request_id', $this->partRequestId)
            ->get(['id', 'vendor_response_id', 'buyer_price', 'is_free']);

        if ($presentedQuotes->isEmpty()) {
            return [];
        }

        $vendorResponses = VendorResponse::query()
            ->select(['id', 'quality_rank'])
            ->with('photos')
            ->whereIn('id', $presentedQuotes->pluck('vendor_response_id'))
            ->get()
            ->keyBy('id');

        return $presentedQuotes
            ->map(fn (PresentedQuote $presentedQuote) => [
                'presented_quote_id' => $presentedQuote->id,
                'buyer_price' => $presentedQuote->buyer_price,
                'quality_rank' => $vendorResponses[$presentedQuote->vendor_response_id]->quality_rank,
                'photos' => $vendorResponses[$presentedQuote->vendor_response_id]->photos->map(fn ($photo) => $photo->url())->all(),
                'is_selected' => $presentedQuote->vendor_response_id === $selectedResponseId,
                'is_free' => $presentedQuote->is_free,
            ])
            ->all();
    }
}
