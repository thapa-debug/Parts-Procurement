<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\PaymentFailedException;
use App\Models\BuyerAddress;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 有償 checkout (CLAUDE.md §14 Phase 4): the buyer's final step after
 * picking a presented quote -- choose a saved shipping address, pay, and
 * the request becomes `paid`.
 *
 * There is no shipping method/fee decision here any more (CLAUDE.md §14
 * Phase 4, rule-based shipping v1): SelectQuoteAction already copied the
 * fee ShippingCalculator computed at presentation time (or the admin's
 * override) onto part_requests.shipping_fee/shipping_method the moment
 * the buyer picked their quote. This action only ever reads that
 * already-fixed figure -- it never computes or accepts one.
 *
 * Everything -- the shipping-address snapshot
 * (SnapshotShippingAddressAction), the payment row, and the status
 * transition -- happens in one DB transaction (CLAUDE.md §8: wrap every
 * multi-write operation in a transaction). A failed charge rolls back all
 * of it: no partial snapshot, no status change, and no stray
 * pending/failed payment row left behind -- the same all-or-nothing shape
 * as SubmitVendorResponseAction's response+photos.
 *
 * Refuses to run against a 無償 (free) request (CLAUDE.md §14 Phase 4
 * slice 5) -- one that has nothing to charge belongs to
 * ConfirmFreeOrderAction instead, which opens CLAUDE.md §6.3's payment
 * gate the same way but without a real gateway charge.
 */
class CheckoutAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly SnapshotShippingAddressAction $snapshotShippingAddress,
    ) {}

    public function execute(PartRequest $partRequest, BuyerAddress $address): PartRequest
    {
        if ($partRequest->is_free) {
            throw CheckoutNotAllowedException::isFreeRequest($partRequest);
        }

        if ($partRequest->status !== RequestStatus::Quoted || $partRequest->selected_response_id === null) {
            throw CheckoutNotAllowedException::noQuoteSelected($partRequest);
        }

        if ($partRequest->shipping_fee === null) {
            throw CheckoutNotAllowedException::shippingFeeMissing($partRequest);
        }

        return DB::transaction(function () use ($partRequest, $address) {
            $this->snapshotShippingAddress->execute($partRequest, $address);

            $payment = Payment::create([
                'part_request_id' => $partRequest->id,
                'amount' => $partRequest->buyer_price + $partRequest->shipping_fee,
                'currency' => 'JPY',
                'status' => PaymentStatus::Pending,
                'gateway' => $this->gateway->name(),
            ]);

            $result = $this->gateway->charge($payment);

            if (! $result->successful) {
                // Never log raw_response here -- CLAUDE.md §11: no full
                // gateway payloads in logs, even on failure.
                Log::channel('payments')->warning('Checkout charge failed', [
                    'part_request_id' => $partRequest->id,
                    'payment_id' => $payment->id,
                    'gateway' => $this->gateway->name(),
                ]);

                throw PaymentFailedException::chargeFailed($partRequest);
            }

            $partRequest->update(['status' => RequestStatus::Paid]);

            Log::channel('payments')->info('Checkout payment confirmed', [
                'part_request_id' => $partRequest->id,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'gateway' => $this->gateway->name(),
            ]);

            return $partRequest->fresh();
        });
    }
}
