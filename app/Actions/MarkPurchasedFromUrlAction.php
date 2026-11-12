<?php

namespace App\Actions;

use App\Enums\RequestStatus;
use App\Exceptions\PaymentNotConfirmedException;
use App\Exceptions\UrlSourcingNotAllowedException;
use App\Models\PartRequest;
use Illuminate\Support\Facades\DB;

/**
 * Reference-URL flow, fulfilment: the admin has bought the part from the
 * reference URL -- the URL flow's counterpart of
 * ConfirmOrderToVendorAction, with no vendor involved. It moves the
 * request to the same `ordered_to_vendor` (発注確定) status, so shipping
 * and everything after it is the existing flow; is_url_sourced is what
 * tells the two apart.
 *
 * CLAUDE.md §6.3's pay-before-purchase gate applies unchanged: it reads
 * the same PartRequest::paymentGateOpen() as ConfirmOrderToVendorAction
 * (`paid` AND a genuinely confirmed payment) and throws the same
 * PaymentNotConfirmedException. A refused call writes nothing.
 *
 * $reference is the admin's optional note (the site's order number, a
 * receipt reference, ...) -- admin-internal, kept on the request and in
 * the activity log.
 */
class MarkPurchasedFromUrlAction
{
    public function execute(PartRequest $partRequest, ?string $reference = null): PartRequest
    {
        return DB::transaction(function () use ($partRequest, $reference) {
            // Locked and re-checked, so a double-click can't run it twice.
            $partRequest = PartRequest::query()->lockForUpdate()->findOrFail($partRequest->id);

            if (! $partRequest->is_url_sourced) {
                throw UrlSourcingNotAllowedException::notUrlSourced($partRequest);
            }

            if (! $partRequest->paymentGateOpen()) {
                throw PaymentNotConfirmedException::forRequest($partRequest);
            }

            $reference = trim((string) $reference);

            $partRequest->update([
                'status' => RequestStatus::OrderedToVendor,
                'url_purchase_reference' => $reference === '' ? null : $reference,
            ]);

            // Who purchased it, and against what reference (CLAUDE.md §11).
            activity()
                ->performedOn($partRequest)
                ->event('purchased_from_url')
                ->withProperties(['url_purchase_reference' => $partRequest->url_purchase_reference])
                ->log('Purchased from reference URL');

            return $partRequest->fresh();
        });
    }
}
