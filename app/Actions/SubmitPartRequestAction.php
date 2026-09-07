<?php

namespace App\Actions;

use App\Enums\PartType;
use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use App\Models\User;
use App\Notifications\PartRequestSubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Buyer request submission. request_code is generated from the row's own
 * id (PartRequest::generateRequestCode), which doesn't exist until after
 * insert -- same two-step create-then-update shape as
 * BuyerProfile::generateMemberCode's caller, wrapped in one transaction so
 * a request never persists without its code.
 */
class SubmitPartRequestAction
{
    public function execute(
        BuyerProfile $buyer,
        PartType $partType,
        int $makerId,
        string $carModel,
        string $vin,
        ?string $oemPartNumber,
        string $partName,
        ?string $referenceUrl,
        ?string $memo,
    ): PartRequest {
        $partRequest = DB::transaction(function () use ($buyer, $partType, $makerId, $carModel, $vin, $oemPartNumber, $partName, $referenceUrl, $memo) {
            $partRequest = PartRequest::create([
                'buyer_id' => $buyer->id,
                'request_code' => null, // filled in below, once the id exists
                'part_type' => $partType,
                'maker_id' => $makerId,
                'car_model' => $carModel,
                'vin' => $vin,
                'oem_part_number' => $oemPartNumber,
                'part_name' => $partName,
                'reference_url' => $referenceUrl,
                'memo' => $memo,
                'status' => RequestStatus::New,
            ]);

            $partRequest->update(['request_code' => PartRequest::generateRequestCode($partRequest->id)]);

            return $partRequest;
        });

        // Best-effort: a failed send must never undo a successful submission
        // (CLAUDE.md §10) -- same try/catch(Throwable)+report() shape as
        // RegisterBuyerAction's verification email.
        try {
            Notification::send(User::query()->admins()->get(), new PartRequestSubmittedNotification($partRequest));
        } catch (Throwable $e) {
            report($e);
        }

        return $partRequest;
    }
}
