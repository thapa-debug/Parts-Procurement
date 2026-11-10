<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\CheckoutTotalChangedException;
use App\Exceptions\PaymentFailedException;
use App\Models\BuyerAddress;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Payments\PaymentGateway;
use App\Services\ShippingFeeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 有償 checkout (CLAUDE.md §14 Phase 4): the buyer's final step after
 * picking a presented quote -- choose a saved shipping address and start
 * the payment. The request becomes `paid` only if the payment is already
 * confirmed when charge() returns (the stub gateway, dev/test only).
 * Otherwise -- Stripe, where charge() only creates a PaymentIntent -- it
 * becomes `awaiting_payment`, and ConfirmStripePaymentAction moves it to
 * `paid` once Stripe's signed webhook confirms the charge. A buyer who
 * abandons at the card form is left at `awaiting_payment`, never `paid`.
 *
 * The buyer's shipping method is chosen here (Regular, the default, or
 * DHL), and its fee comes from ShippingFeeService -- never from the
 * browser. The method and fee are snapshotted onto the request BEFORE the
 * payment row is created and charged, so the PaymentIntent is always
 * created for the final total. Once this succeeds the request leaves
 * `quoted`, so the method can't change any more (the same lock as the
 * quote choice). $expectedTotal is the total the buyer was shown; if it
 * no longer matches, nothing is charged.
 *
 * Everything -- the shipping-address snapshot
 * (SnapshotShippingAddressAction), the shipping method/fee snapshot, the
 * payment row, and the status
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
        private readonly SendPaymentConfirmedNotificationsAction $sendPaymentConfirmedNotifications,
        private readonly ShippingFeeService $shippingFees,
    ) {}

    public function execute(
        PartRequest $partRequest,
        BuyerAddress $address,
        ShippingMethod $shippingMethod = ShippingMethod::Standard,
        ?int $expectedTotal = null,
    ): PartRequest {
        if ($partRequest->is_free) {
            throw CheckoutNotAllowedException::isFreeRequest($partRequest);
        }

        if ($partRequest->status !== RequestStatus::Quoted || $partRequest->selected_response_id === null) {
            throw CheckoutNotAllowedException::noQuoteSelected($partRequest);
        }

        if ($partRequest->shipping_fee === null) {
            throw CheckoutNotAllowedException::shippingFeeMissing($partRequest);
        }

        [$partRequest, $payment] = DB::transaction(function () use ($partRequest, $address, $shippingMethod, $expectedTotal): array {
            // First, so the address is proven to be this buyer's before
            // anything (e.g. a future DHL rate call) uses it.
            $this->snapshotShippingAddress->execute($partRequest, $address);

            $shippingFee = $this->shippingFees->feeFor($partRequest, $shippingMethod, $address);
            $total = $partRequest->buyer_price + $shippingFee;

            if ($expectedTotal !== null && $expectedTotal !== $total) {
                throw CheckoutTotalChangedException::forTotals($partRequest, $expectedTotal, $total);
            }

            $partRequest->update([
                'shipping_method' => $shippingMethod,
                'shipping_fee' => $shippingFee,
            ]);

            $payment = Payment::create([
                'part_request_id' => $partRequest->id,
                'amount' => $total,
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

            // charge() succeeding is not the same as being paid: re-read
            // the payment, since only a synchronously-confirming gateway
            // (the stub) has confirmed it by now.
            $confirmed = $payment->fresh()->status === PaymentStatus::Confirmed;

            $partRequest->update([
                'status' => $confirmed ? RequestStatus::Paid : RequestStatus::AwaitingPayment,
            ]);

            Log::channel('payments')->info($confirmed ? 'Checkout payment confirmed' : 'Checkout payment started, awaiting confirmation', [
                'part_request_id' => $partRequest->id,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'shipping_method' => $shippingMethod->value,
                'gateway' => $this->gateway->name(),
            ]);

            return [$partRequest->fresh(), $payment];
        });

        // The stub gateway confirms synchronously inside charge() (dev/
        // test only) -- Stripe never does (ConfirmStripePaymentAction,
        // webhook-only, handles that path instead, CLAUDE.md §14 stripe
        // integration). $payment was read inside the transaction, before
        // charge() had necessarily updated it in every gateway's case, so
        // it's re-checked fresh here rather than trusted stale.
        if ($payment->fresh()->status === PaymentStatus::Confirmed) {
            $this->sendPaymentConfirmedNotifications->execute($payment);
        }

        return $partRequest;
    }
}
