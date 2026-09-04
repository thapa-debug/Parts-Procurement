<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Self-registration (CLAUDE.md 14): the buyer picks their own password and
 * can log in immediately -- no temporary password, no forced password
 * change. Deliberately separate from CreateBuyerAction (the admin-created
 * path): different actor (none, vs an authenticated admin), different
 * password handling, and this path also has to get the account verified,
 * which the admin-created path defers to the user's first login.
 */
class RegisterBuyerAction
{
    /**
     * @return array{user: User, buyer_profile: BuyerProfile}
     */
    public function execute(
        string $name,
        string $email,
        string $password,
        string $companyName,
        int $countryId,
        string $defaultYard,
        string $phone,
    ): array {
        $result = DB::transaction(function () use ($name, $email, $password, $companyName, $countryId, $defaultYard, $phone) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => UserRole::Buyer,
                'is_active' => true,
                'must_change_password' => false,
            ]);

            $buyerProfile = BuyerProfile::create([
                'user_id' => $user->id,
                'company_name' => $companyName,
                'member_code' => BuyerProfile::generateMemberCode($user),
                'country_id' => $countryId,
                'default_yard' => $defaultYard,
                'phone' => $phone,
            ]);

            return ['user' => $user, 'buyer_profile' => $buyerProfile];
        });

        // Best-effort: a failed send must never undo a successful registration
        // (CLAUDE.md 10) -- the user can always ask for another link via the
        // verification banner's resend button.
        try {
            $result['user']->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            report($e);
        }

        return $result;
    }
}
