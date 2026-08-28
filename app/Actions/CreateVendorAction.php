<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Vendor-specific onboarding: composes the shared, role-agnostic
 * CreateAdminManagedUserAction with the vendor_profiles row it needs
 * alongside it. Wrapped in a transaction so a failure creating the profile
 * never leaves an orphaned user behind.
 *
 * Auto-sends the verification email once the transaction commits (CLAUDE.md
 * §14): the account still starts unverified and still can't act until the
 * link is followed -- this only removes the admin's manual "Resend
 * verification" click as the default path. That action still exists as a
 * fallback for a bounced or lost email.
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
        $result = DB::transaction(function () use ($name, $email, $companyName, $contactPerson, $phone, $notifyEmail) {
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

        // Best-effort: a failed send must never undo a successful creation
        // (CLAUDE.md §10) -- "Resend verification email" on the master list
        // covers a bounced or lost send.
        try {
            $result['user']->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            report($e);
        }

        return $result;
    }
}
