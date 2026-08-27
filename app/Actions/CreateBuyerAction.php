<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Buyer-specific onboarding: composes the shared, role-agnostic
 * CreateAdminManagedUserAction with the buyer_profiles row it needs
 * alongside it. Wrapped in a transaction so a failure creating the profile
 * never leaves an orphaned user behind. Mirrors CreateVendorAction.
 *
 * Admin-created path only -- see RegisterBuyerAction for self-registration,
 * which differs too much (user-chosen password, no forced change, no admin
 * actor to authorize against) to share this action.
 */
class CreateBuyerAction
{
    public function __construct(
        private readonly CreateAdminManagedUserAction $createAdminManagedUser,
    ) {}

    /**
     * @return array{user: User, buyer_profile: BuyerProfile, temporary_password: string}
     */
    public function execute(
        string $name,
        string $email,
        string $companyName,
        string $defaultDestinationCountry,
        string $defaultYard,
        string $phone,
    ): array {
        return DB::transaction(function () use ($name, $email, $companyName, $defaultDestinationCountry, $defaultYard, $phone) {
            $result = $this->createAdminManagedUser->execute($name, $email, UserRole::Buyer);

            $buyerProfile = BuyerProfile::create([
                'user_id' => $result['user']->id,
                'company_name' => $companyName,
                'member_code' => BuyerProfile::generateMemberCode($result['user']),
                'default_destination_country' => $defaultDestinationCountry,
                'default_yard' => $defaultYard,
                'phone' => $phone,
            ]);

            return [
                'user' => $result['user'],
                'buyer_profile' => $buyerProfile,
                'temporary_password' => $result['temporary_password'],
            ];
        });
    }
}
