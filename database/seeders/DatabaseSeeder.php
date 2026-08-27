<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Local-dev demo data with fixed, known credentials -- deliberately NOT built
 * through CreateAdminManagedUserAction/CreateVendorAction, since both exist
 * specifically to generate an unguessable random temporary password. Seed
 * data needs the opposite: the same login on every `migrate:fresh --seed`.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => UserRole::Admin,
        ]);

        $activeVendorUser = User::factory()->create([
            'name' => 'Active Vendor',
            'email' => 'vendor.active@example.com',
            'password' => 'password',
            'role' => UserRole::Vendor,
            'must_change_password' => false,
        ]);

        VendorProfile::factory()->create([
            'user_id' => $activeVendorUser->id,
            'company_name' => 'Active Motors Dismantlers',
            'contact_person' => 'Active Vendor',
            'phone' => '090-0000-0001',
            'notify_email' => 'vendor.active@example.com',
            'status' => VendorStatus::Active,
        ]);

        // Mirrors real admin-created onboarding (CreateAdminManagedUserAction):
        // must_change_password true, email unverified -- but with a fixed,
        // known temporary password instead of a random one, so it can
        // actually be typed in to demo the forced password-change screen.
        $pendingVendorUser = User::factory()->unverified()->create([
            'name' => 'Pending Vendor',
            'email' => 'vendor.pending@example.com',
            'password' => 'ChangeMe!2026',
            'role' => UserRole::Vendor,
            'must_change_password' => true,
        ]);

        VendorProfile::factory()->create([
            'user_id' => $pendingVendorUser->id,
            'company_name' => 'Pending Onboarding Motors',
            'contact_person' => 'Pending Vendor',
            'phone' => '090-0000-0002',
            'notify_email' => 'vendor.pending@example.com',
            'status' => VendorStatus::Active,
        ]);
    }
}
