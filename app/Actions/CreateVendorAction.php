<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

/**
 * Vendor-specific onboarding: composes the shared, role-agnostic
 * CreateAdminManagedUserAction with the vendor_profiles row it needs
 * alongside it. Wrapped in a transaction so a failure creating the profile
 * never leaves an orphaned user behind.
 */
class CreateVendorAction
{
    public function __construct(
        private readonly CreateAdminManagedUserAction $createAdminManagedUser,
    ) {}

    /**
     * @return array{user: User, vendor_profile: VendorProfile, temporary_password: string}
     */
    public function execute(
        string $name,
        string $email,
        string $companyName,
        string $contactPerson,
        string $phone,
        string $notifyEmail,
    ): array {
        return DB::transaction(function () use ($name, $email, $companyName, $contactPerson, $phone, $notifyEmail) {
            $result = $this->createAdminManagedUser->execute($name, $email, UserRole::Vendor);

            $vendorProfile = VendorProfile::create([
                'user_id' => $result['user']->id,
                'company_name' => $companyName,
                'contact_person' => $contactPerson,
                'phone' => $phone,
                'notify_email' => $notifyEmail,
            ]);

            return [
                'user' => $result['user'],
                'vendor_profile' => $vendorProfile,
                'temporary_password' => $result['temporary_password'],
            ];
        });
    }
}
