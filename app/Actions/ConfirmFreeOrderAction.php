<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\FreeOrderNotAllowedException;
use App\Models\BuyerAddress;
use App\Models\PartRequest;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 無償 (free) checkout (CLAUDE.md §14 Phase 4 slice 5): the mirror of
 * CheckoutAction for a request an admin has marked free at presentation
 * time. There is nothing to charge -- the buyer still confirms/picks a
 * shipping address (the same reasoning as a paid order: their default
 * address isn't necessarily right for this particular shipment), but the
 * step ends there.
 *
 * CLAUDE.md §6.3's payment gate (ConfirmOrderToVendorAction requires a
 * `confirmed` payment on a `paid` request) still has to open for a free
 * request -- the admin still needs to purchase from the vendor and ship
 * it. Rather than special-case the gate itself, this action satisfies it
 * the same way a real charge would: it creates a genuine Payment row,
 * amount 0, status Confirmed, gateway 'waived' -- so
 * ConfirmOrderToVendorAction's own check needs no changes at all. The ¥0
 * amount is not a coincidence: cost_price/applied_rate/applied_min_fee on
 * the request are still the real, normally-computed figures (copied from
 * the presented quote by SelectQuoteAction) -- only what the buyer owes is
 * zero, which is exactly what this payment row records.
 *
 * Same all-or-nothing DB transaction shape as CheckoutAction: the address
 * snapshot, the payment row, and the status transition either all happen
 * or none do.
 */
class ConfirmFreeOrderAction
{
    public function __construct(
        private readonly SnapshotShippingAddressAction $snapshotShippingAddress,
        private readonly SendPaymentConfirmedNotificationsAction $sendPaymentConfirmedNotifications,
    ) {}

    public function execute(PartRequest $partRequest, BuyerAddress $address): PartRequest
    {
        if (! $partRequest->is_free) {
            throw FreeOrderNotAllowedException::notFree($partRequest);
        }

        if ($partRequest->status !== RequestStatus::Quoted || $partRequest->selected_response_id === null) {
            throw FreeOrderNotAllowedException::noQuoteSelected($partRequest);
        }

        [$partRequest, $payment] = DB::transaction(function () use ($partRequest, $address) {
            $this->snapshotShippingAddress->execute($partRequest, $address);

            $payment = Payment::create([
                'part_request_id' => $partRequest->id,
                'amount' => 0,
                'currency' => 'JPY',
                'status' => PaymentStatus::Confirmed,
                'gateway' => 'waived',
                'paid_at' => now(),
            ]);

            $partRequest->update(['status' => RequestStatus::Paid]);

            Log::channel('payments')->info('Free (無償) order confirmed, no payment charged', [
                'part_request_id' => $partRequest->id,
                'payment_id' => $payment->id,
            ]);

            return [$partRequest->fresh(), $payment];
        });

        $this->sendPaymentConfirmedNotifications->execute($payment);

        return $partRequest;
    }
}
