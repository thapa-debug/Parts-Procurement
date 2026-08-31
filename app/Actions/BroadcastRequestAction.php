<?php

namespace App\Actions;

use App\Enums\RequestStatus;
use App\Enums\VendorStatus;
use App\Exceptions\RequestCannotBeBroadcastException;
use App\Models\PartRequest;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

/**
 * 打診: broadcasts a `new` request to the selected vendors -- records each
 * as a request_vendor pivot row (invited_at) and transitions the request
 * to vendor_inquiry. Every vendor id is re-checked against VendorProfile
 * here, not just trusted from the caller's selection list: a suspended
 * vendor must never end up invited, even if one went from active to
 * suspended in the moment between the admin loading the page and
 * submitting (CONVENTIONS.md "Vendor status is a flag only").
 */
class BroadcastRequestAction
{
    /**
     * @param  array<int, int>  $vendorProfileIds
     */
    public function execute(PartRequest $partRequest, array $vendorProfileIds): PartRequest
    {
        if ($partRequest->status !== RequestStatus::New) {
            throw RequestCannotBeBroadcastException::wrongStatus($partRequest);
        }

        $eligibleVendorIds = VendorProfile::query()
            ->where('status', VendorStatus::Active)
            ->whereIn('id', $vendorProfileIds)
            ->pluck('id');

        if ($eligibleVendorIds->isEmpty()) {
            throw RequestCannotBeBroadcastException::noEligibleVendors();
        }

        return DB::transaction(function () use ($partRequest, $eligibleVendorIds) {
            $now = now();

            $partRequest->vendors()->attach(
                $eligibleVendorIds->mapWithKeys(fn (int $id) => [$id => ['invited_at' => $now]])->all()
            );

            $partRequest->update(['status' => RequestStatus::VendorInquiry]);

            return $partRequest->fresh();
        });
    }
}
