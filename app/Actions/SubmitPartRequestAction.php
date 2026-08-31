<?php

namespace App\Actions;

use App\Enums\PartType;
use App\Enums\RequestStatus;
use App\Models\BuyerProfile;
use App\Models\PartRequest;
use Illuminate\Support\Facades\DB;

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
        string $maker,
        string $carModel,
        string $vin,
        ?string $oemPartNumber,
        string $partName,
        ?string $referenceUrl,
        ?string $memo,
    ): PartRequest {
        return DB::transaction(function () use ($buyer, $partType, $maker, $carModel, $vin, $oemPartNumber, $partName, $referenceUrl, $memo) {
            $partRequest = PartRequest::create([
                'buyer_id' => $buyer->id,
                'request_code' => null, // filled in below, once the id exists
                'part_type' => $partType,
                'maker' => $maker,
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
    }
}
