<?php

namespace App\Actions;

use App\Enums\RequestStatus;
use App\Exceptions\PaymentNotConfirmedException;
use App\Exceptions\UrlSourcingNotAllowedException;
use App\Models\CompanyAddress;
use App\Models\PartRequest;
use App\Notifications\OrderConfirmedToVendorNotification;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The admin purchases from the chosen vendor, once the buyer's payment is
 * confirmed (CLAUDE.md §6.3's hard payment gate -- money-critical). The
 * vendor is derived from the request's own selectedResponse, not passed in
 * separately: CheckoutAction already requires selected_response_id to be
 * set before a request can reach `paid`, so which vendor to confirm to is
 * never a separate decision at this point.
 *
 * The admin also chooses which of the company's own receiving locations
 * the vendor should deliver the part to (CLAUDE.md §14 Phase 4 slice 6).
 * Its fields are snapshotted onto the request's receiving_* columns --
 * the same discipline as SnapshotShippingAddressAction for the buyer's
 * address -- so a later edit or delete of the CompanyAddress never changes
 * where this order told the vendor to deliver. The gate runs first: a
 * refused confirmation writes nothing at all.
 *
 * Once committed, the confirmed vendor is notified (発注確定, in-app +
 * mail) -- how they learn they were chosen and where to deliver.
 *
 * A reference-URL (admin-sourced) request has no vendor to confirm to:
 * it is refused here and goes through MarkPurchasedFromUrlAction instead.
 */
class ConfirmOrderToVendorAction
{
    public function execute(PartRequest $partRequest, CompanyAddress $receivingAddress): PartRequest
    {
        if ($partRequest->is_url_sourced) {
            throw UrlSourcingNotAllowedException::isUrlSourced($partRequest);
        }

        if (! $partRequest->paymentGateOpen()) {
            throw PaymentNotConfirmedException::forRequest($partRequest);
        }

        DB::transaction(function () use ($partRequest, $receivingAddress) {
            $partRequest->update([
                'status' => RequestStatus::OrderedToVendor,
                'confirmed_vendor_id' => $partRequest->selectedResponse?->vendor_id,
                'receiving_address_id' => $receivingAddress->id,
                'receiving_label' => $receivingAddress->label,
                'receiving_recipient_name' => $receivingAddress->recipient_name,
                'receiving_phone' => $receivingAddress->phone,
                'receiving_postal_code' => $receivingAddress->postal_code,
                'receiving_prefecture' => $receivingAddress->prefecture,
                'receiving_city' => $receivingAddress->city,
                'receiving_address_line1' => $receivingAddress->address_line1,
                'receiving_address_line2' => $receivingAddress->address_line2,
            ]);

            // Who confirmed which vendor purchase, and to where (CLAUDE.md
            // §11: order confirmations belong in the audit trail).
            activity()
                ->performedOn($partRequest)
                ->event('order_confirmed_to_vendor')
                ->withProperties([
                    'confirmed_vendor_id' => $partRequest->confirmed_vendor_id,
                    'receiving_address_id' => $receivingAddress->id,
                    'receiving_label' => $receivingAddress->label,
                ])
                ->log('Order confirmed to vendor');
        });

        $partRequest = $partRequest->fresh();

        // Best-effort: a failed send must never undo a confirmed order
        // (CLAUDE.md §10) -- same try/catch(Throwable)+report() shape as
        // PresentQuoteAction's buyer notification.
        try {
            $partRequest->confirmedVendor?->user?->notify(new OrderConfirmedToVendorNotification($partRequest));
        } catch (Throwable $e) {
            report($e);
        }

        return $partRequest;
    }
}
