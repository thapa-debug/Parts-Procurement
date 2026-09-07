<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Buyer-specific onboarding: composes the shared, role-agnostic
 * CreateAdminManagedUserAction with the buyer_profiles row it needs
 * alongside it. Wrapped in a transaction so a failure creating the profile
 * never leaves an orphaned user behind. Mirrors CreateVendorAction,
 * including the auto-sent verification email once the transaction commits.
 *
 * Admin-created path only -- see RegisterBuyerAction for self-registration,
 * which differs too much (user-chosen password, no forced change, no admin
 * actor to authorize against) to share this action.
 *
 * $approveImmediately (CLAUDE.md §14's buyer-approval gate) lets the admin
 * choose "activate now" (the common case, since the admin is deliberately
 * creating a known buyer) vs. "create but hold" (lands in the pending
 * queue for a later, separate approval). Either way this is independent of
 * email verification -- the account still starts unverified and still
 * can't act until the link is followed, regardless of approval state. See
 * AppServiceProvider's `act` gate for how the two conditions combine.
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
        int $countryId,
        string $phone,
        User $admin,
        bool $approveImmediately,
    ): array {
        $result = DB::transaction(function () use ($name, $email, $companyName, $countryId, $phone, $admin, $approveImmediately) {
            $result = $this->createAdminManagedUser->execute($name, $email, UserRole::Buyer);

            $buyerProfile = BuyerProfile::create([
                'user_id' => $result['user']->id,
                'company_name' => $companyName,
                'member_code' => BuyerProfile::generateMemberCode($result['user']),
                'country_id' => $countryId,
                'phone' => $phone,
                'approved_at' => $approveImmediately ? now() : null,
                'approved_by' => $approveImmediately ? $admin->id : null,
            ]);

            return [
                'user' => $result['user'],
                'buyer_profile' => $buyerProfile,
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
