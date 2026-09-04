<?php

namespace App\Livewire\Buyer;

use App\Enums\QualityRank;
use App\Models\PartRequest;
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

    public function render(): View
    {
        // Fetched separately from the main query below, and never selected
        // onto the $partRequest passed to the view: a raw internal FK to a
        // vendor_response row has no reason to ever reach the buyer's
        // browser, even though it isn't "vendor identity" by itself.
        $selectedResponseId = PartRequest::query()->where('id', $this->partRequestId)->value('selected_response_id');

        // Explicit column allowlist -- cost_price, applied_rate,
        // applied_min_fee, and selected_response_id are deliberately absent.
        // buyer_price is the one price column a buyer is ever allowed to see.
        $partRequest = PartRequest::query()
            ->select([
                'id', 'request_code', 'part_type', 'maker_id', 'car_model', 'vin',
                'oem_part_number', 'part_name', 'reference_url', 'memo',
                'status', 'buyer_price', 'created_at',
            ])
            ->with('maker')
            ->findOrFail($this->partRequestId);

        $quote = $this->presentedQuote($selectedResponseId);

        return view('livewire.buyer.request-detail', [
            'partRequest' => $partRequest,
            'quote' => $quote,
        ])->title($partRequest->request_code);
    }

    /**
     * Builds a plain, hand-picked array -- not the VendorResponse model --
     * so there is no property on it a future Blade edit could accidentally
     * reach for `->vendor` or `->cost_price` through. Only what CLAUDE.md §4
     * allows a buyer to see: the photo(s) and the quality rank. Never the
     * comment (free text a vendor writes themselves, with no practical way
     * to guarantee it never mentions their own name) and never lead_time --
     * neither was asked for in the buyer-facing spec for this quote view.
     *
     * @return array{quality_rank: QualityRank, photos: array<int, string>}|null
     */
    protected function presentedQuote(?int $selectedResponseId): ?array
    {
        if ($selectedResponseId === null) {
            return null;
        }

        $vendorResponse = VendorResponse::query()
            ->select(['id', 'quality_rank'])
            ->with('photos')
            ->find($selectedResponseId);

        if (! $vendorResponse) {
            return null;
        }

        return [
            'quality_rank' => $vendorResponse->quality_rank,
            'photos' => $vendorResponse->photos->map(fn ($photo) => $photo->url())->all(),
        ];
    }
}
