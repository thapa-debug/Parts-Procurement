<?php

use App\Actions\ConfirmOrderToVendorAction;
use App\Enums\DeliveryMethod;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\CompanyAddress;
use App\Models\PartRequest;
use App\Models\Payment;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

// --- shared helpers ------------------------------------------------------------

// Shared by the vendor RequestResponse and Inbox tests -- the confirmed-order
// setup both screens' isolation tests build on.
/**
 * Two invited vendors, both responded; $confirmed's response was selected,
 * paid for, and confirmed to the Chiba yard through the real Action. The
 * buyer has a distinctive company name and shipping address that must never
 * reach either vendor.
 *
 * @return array{0: PartRequest, 1: User, 2: User}
 */
function confirmedOrderWithTwoVendors(DeliveryMethod $deliveryMethod = DeliveryMethod::Deliver): array
{
    $buyer = BuyerProfile::factory()->create(['company_name' => 'Secret Buyer Co']);
    $request = PartRequest::factory()->for($buyer, 'buyer')->create([
        'status' => RequestStatus::Paid,
        'shipping_recipient_name' => 'Buyer Recipient Person',
        'shipping_address_line1' => '42 Buyer Street',
    ]);

    $confirmedUser = User::factory()->vendor()->create();
    $confirmed = VendorProfile::factory()->for($confirmedUser)->create(['delivery_method' => $deliveryMethod]);
    $otherUser = User::factory()->vendor()->create();
    $other = VendorProfile::factory()->for($otherUser)->create();

    foreach ([$confirmed, $other] as $vendor) {
        $request->vendors()->attach($vendor->id, ['invited_at' => now()]);
    }

    $response = VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $confirmed->id]);
    VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $other->id]);
    $request->update(['selected_response_id' => $response->id]);
    Payment::factory()->create(['part_request_id' => $request->id, 'status' => PaymentStatus::Confirmed]);

    $address = CompanyAddress::factory()->create([
        'label' => 'Chiba yard',
        'recipient_name' => 'Receiving desk',
        'phone' => '043-123-4567',
        'postal_code' => '260-0001',
        'prefecture' => 'Chiba',
        'city' => 'Chiba-shi',
        'address_line1' => '1-2-3 Chuo',
    ]);

    app(ConfirmOrderToVendorAction::class)->execute($request->fresh(), $address);

    return [$request->fresh(), $confirmedUser, $otherUser];
}

// Shared by the admin RequestBoard and RequestDetail tests -- the
// "quotes received" chip on a vendor_inquiry request.
/**
 * A request broadcast to $invited vendors, of whom $quotes sent a real
 * quote and $noStock replied "no stock" (the rest haven't replied yet).
 *
 * @param  array<string, mixed>  $attributes
 */
function inquiryWithResponses(int $invited, int $quotes, int $noStock, array $attributes = []): PartRequest
{
    $request = PartRequest::factory()->create(['status' => RequestStatus::VendorInquiry, ...$attributes]);
    $vendors = VendorProfile::factory()->count($invited)->create();

    foreach ($vendors as $i => $vendor) {
        $request->vendors()->attach($vendor->id, ['invited_at' => now()]);

        if ($i < $quotes) {
            VendorResponse::factory()->create(['part_request_id' => $request->id, 'vendor_id' => $vendor->id]);
        } elseif ($i < $quotes + $noStock) {
            VendorResponse::factory()->noStock()->create(['part_request_id' => $request->id, 'vendor_id' => $vendor->id]);
        }
    }

    return $request;
}
