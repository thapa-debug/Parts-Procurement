<?php

namespace App\Actions;

use App\Enums\RequestStatus;
use App\Enums\VendorStatus;
use App\Exceptions\RequestCannotBeBroadcastException;
use App\Models\PartRequest;
use App\Models\VendorProfile;
use App\Notifications\RequestBroadcastNotification;
use Illuminate\Support\Facades\DB;
use Throwable;

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

        $eligibleVendors = VendorProfile::query()
            ->where('status', VendorStatus::Active)
            ->whereIn('id', $vendorProfileIds)
            ->with('user')
            ->get();

        if ($eligibleVendors->isEmpty()) {
            throw RequestCannotBeBroadcastException::noEligibleVendors();
        }

        $partRequest = DB::transaction(function () use ($partRequest, $eligibleVendors) {
            $now = now();

            $partRequest->vendors()->attach(
                $eligibleVendors->mapWithKeys(fn (VendorProfile $vendor) => [$vendor->id => ['invited_at' => $now]])->all()
            );

            $partRequest->update(['status' => RequestStatus::VendorInquiry]);

            return $partRequest->fresh();
        });

        // Best-effort, per vendor: one vendor's notification failing must
        // never stop the others from being notified, and none of them may
        // ever roll back the broadcast itself (CLAUDE.md §10).
        foreach ($eligibleVendors as $vendor) {
            try {
                $vendor->user?->notify(new RequestBroadcastNotification($partRequest));
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $partRequest;
    }
}
