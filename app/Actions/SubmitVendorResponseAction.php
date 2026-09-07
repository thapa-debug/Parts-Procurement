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
     * @param  array{cost_price?: int|null, quality_rank?: string|null, lead_time?: string|null, comment?: string|null, weight_kg?: float|null, length_cm?: float|null, width_cm?: float|null, height_cm?: float|null, is_no_stock?: bool}  $data
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
                'weight_kg' => $data['weight_kg'] ?? null,
                'length_cm' => $data['length_cm'] ?? null,
                'width_cm' => $data['width_cm'] ?? null,
                'height_cm' => $data['height_cm'] ?? null,
                'is_no_stock' => $data['is_no_stock'] ?? false,
            ]);

            // The default filesystem disk (CLAUDE.md §2: S3 in production,
            // per config/filesystems.php -- local dev can point
            // FILESYSTEM_DISK at 'public' instead, same local-only-override
            // shape as CONVENTIONS.md's Redis/mail sections). Read once and
            // reused for both the store() call and the persisted `disk`
            // column so a photo is never saved to one disk while its row
            // claims another.
            $disk = config('filesystems.default');

            foreach ($photos as $photo) {
                $path = $photo->store('response-photos', $disk);

                $response->photos()->create([
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $photo->getClientOriginalName(),
                    'size' => $photo->getSize(),
                ]);
            }

            return $response->fresh('photos');
        });
    }
}
