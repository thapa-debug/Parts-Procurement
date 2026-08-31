<?php

namespace App\Actions;

use App\Exceptions\VendorResponseNotAllowedException;
use App\Models\PartRequest;
use App\Models\VendorProfile;
use App\Models\VendorResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Records a vendor's reply to a 打診 broadcast -- either a priced quote
 * (cost/rank/lead time/comment/photos) or a one-tap "no stock" reply.
 * Re-checks the vendor was actually invited to this request and hasn't
 * already responded, rather than trusting the caller/UI -- the same
 * defense-in-depth shape as BroadcastRequestAction re-checking vendor
 * status itself.
 *
 * No request-status guard: CLAUDE.md's broadcast has "no response
 * deadline", and nothing in this slice says a vendor's reply should stop
 * being accepted once the admin has quoted/paid/etc -- e.g. a backup quote
 * could still be useful after a procurement_failed re-quote. If that
 * turns out wrong, add the guard alongside whichever future slice needs it.
 */
class SubmitVendorResponseAction
{
    /**
     * @param  array{cost_price?: int|null, quality_rank?: string|null, lead_time?: string|null, comment?: string|null, is_no_stock?: bool}  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function execute(PartRequest $partRequest, VendorProfile $vendorProfile, array $data, array $photos = []): VendorResponse
    {
        $invited = $partRequest->vendors()->where('vendor_profiles.id', $vendorProfile->id)->exists();

        if (! $invited) {
            throw VendorResponseNotAllowedException::notInvited();
        }

        $alreadyResponded = VendorResponse::query()
            ->where('part_request_id', $partRequest->id)
            ->where('vendor_id', $vendorProfile->id)
            ->exists();

        if ($alreadyResponded) {
            throw VendorResponseNotAllowedException::alreadyResponded();
        }

        return DB::transaction(function () use ($partRequest, $vendorProfile, $data, $photos) {
            $response = VendorResponse::create([
                'part_request_id' => $partRequest->id,
                'vendor_id' => $vendorProfile->id,
                'cost_price' => $data['cost_price'] ?? null,
                'quality_rank' => $data['quality_rank'] ?? null,
                'lead_time' => $data['lead_time'] ?? null,
                'comment' => $data['comment'] ?? null,
                'is_no_stock' => $data['is_no_stock'] ?? false,
            ]);

            foreach ($photos as $photo) {
                $path = $photo->store('response-photos', 's3');

                $response->photos()->create([
                    'disk' => 's3',
                    'path' => $path,
                    'original_name' => $photo->getClientOriginalName(),
                    'size' => $photo->getSize(),
                ]);
            }

            return $response->fresh('photos');
        });
    }
}
