<?php

namespace App\Actions;

use App\Enums\VendorStatus;
use App\Models\VendorProfile;

class SuspendVendorAction
{
    public function execute(VendorProfile $vendorProfile): VendorProfile
    {
        if ($vendorProfile->status !== VendorStatus::Suspended) {
            $vendorProfile->update(['status' => VendorStatus::Suspended]);
        }

        return $vendorProfile;
    }
}
