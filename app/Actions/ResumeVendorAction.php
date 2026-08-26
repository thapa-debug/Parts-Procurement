<?php

namespace App\Actions;

use App\Enums\VendorStatus;
use App\Models\VendorProfile;

class ResumeVendorAction
{
    public function execute(VendorProfile $vendorProfile): VendorProfile
    {
        if ($vendorProfile->status !== VendorStatus::Active) {
            $vendorProfile->update(['status' => VendorStatus::Active]);
        }

        return $vendorProfile;
    }
}
