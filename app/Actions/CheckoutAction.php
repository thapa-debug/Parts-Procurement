<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\ShippingMethod;
use App\Exceptions\CheckoutNotAllowedException;
use App\Exceptions\PaymentFailedException;
use App\Models\BuyerAddress;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\Setting;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 有償 checkout (CLAUDE.md §14 Phase 4 slice 3): the buyer's final step
 * after picking a presented quote (SelectQuoteAction) -- choose a saved
 * shipping address and method, pay, and the request becomes `paid`.
 *
 * Vehicle/container only -- DHL is deliberately not accepted here (its own
 * slice, CLAUDE.md §14 Phase 4 slice 4: a DHL fee depends on the very
 * address this checkout collects, so "enter the fee first" doesn't work).
 * The chosen method's fee is read from live Settings at the moment of
 * checkout and then snapshotted onto part_requests.shipping_fee -- never
 * recomputed afterward if the Settings value changes later, the same
 * discipline as pricing (§6.2).
 *
 * Everything -- the shipping-address snapshot
 * (SnapshotShippingAddressAction), the shipping fee/method, the payment
 * row, and the status transition -- happens in one DB transaction
 * (CLAUDE.md §8: wrap every multi-write operation in a transaction). A
 * failed charge rolls back all of it: no partial snapshot, no fee written,
 * no status change, and no stray pending/failed payment row left behind --
 * the same all-or-nothing shape as SubmitVendorResponseAction's
 * response+photos.
 */
class CheckoutAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly SnapshotShippingAddressAction $snapshotShippingAddress,
    ) {}

    public function execute(PartRequest $partRequest, BuyerAddress $address, ShippingMethod $shippingMethod): PartRequest
    {
        if ($partRequest->status !== RequestStatus::Quoted || $partRequest->selected_response_id === null) {
            throw CheckoutNotAllowedException::noQuoteSelected($partRequest);
        }

        if ($shippingMethod === ShippingMethod::Dhl) {
            throw CheckoutNotAllowedException::dhlNotYetSupported();
        }

        // Dhl is excluded by the guard above -- Larastan narrows
        // $shippingMethod to Vehicle|Container here, so a third arm for it
        // would be unreachable (and flagged as such).
        $shippingFee = match ($shippingMethod) {
            ShippingMethod::Vehicle => (int) Setting::get('shipping_fee_vehicle', 0),
            ShippingMethod::Container => (int) Setting::get('shipping_fee_container', 0),
        };

        return DB::transaction(function () use ($partRequest, $address, $shippingMethod, $shippingFee) {
            $this->snapshotShippingAddress->execute($partRequest, $address);

            $partRequest->update([
                'shipping_method' => $shippingMethod,
                'shipping_fee' => $shippingFee,
            ]);

            $payment = Payment::create([
                'part_request_id' => $partRequest->id,
                'amount' => $partRequest->buyer_price + $shippingFee,
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
