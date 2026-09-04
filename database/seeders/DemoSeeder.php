<?php

namespace Database\Seeders;

use App\Enums\VendorStatus;
use App\Models\BuyerProfile;
use App\Models\Country;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Realistic demo data for client walkthroughs and local development.
 * Every account uses the same known password ("password") so the whole
 * cast can be logged into on demand -- see DEMO.md for the walkthrough
 * and the full credentials list. Kept separate from DatabaseSeeder (which
 * just delegates here) so the demo-specific cast doesn't clutter what
 * would otherwise hold real production seed logic.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Aiko Tanaka',
            'email' => 'admin@demo.test',
            'password' => 'password',
        ]);

        $this->vendor('Yamato Auto Dismantlers', 'Kenichi Sato', 'vendor1@demo.test', '03-1234-5601');
        $this->vendor('Kanto Parts Recycle Co.', 'Daisuke Suzuki', 'vendor2@demo.test', '045-234-5602');
        $this->vendor('Kyushu Used Parts Center', 'Misaki Tanaka', 'vendor3@demo.test', '092-345-5603');
        $this->vendor('Hokkaido Recycle Auto', 'Naoki Ito', 'vendor4@demo.test', '011-456-5604');

        $this->buyer('Global Auto Parts Ltd', 'James Carter', 'buyer1@demo.test', '+971-4-000-1001', 'United Arab Emirates', 'Jebel Ali Yard');
        $this->buyer('Pacific Rim Motors', 'Fatima Al-Sayed', 'buyer2@demo.test', '+64-9-000-1002', 'New Zealand', 'Auckland Yard');

        // Deliberately unverified -- the one account that shows the
        // Unverified badge + resend action on the buyer master list.
        $this->buyer('Southern Cross Auto Imports', "Liam O'Connor", 'buyer3@demo.test', '+61-7-000-1003', 'Australia', 'Brisbane Yard', unverified: true);

        // Deliberately verified but not yet approved -- CLAUDE.md §14's
        // buyer-approval gate: gives the (upcoming) approval-queue screen
        // something real to show.
        $this->buyer('Andes Auto Traders', 'Sofia Herrera', 'buyer4@demo.test', '+56-32-000-1004', 'Chile', 'Valparaiso Yard', pending: true);
    }

    private function vendor(string $companyName, string $contactPerson, string $email, string $phone): void
    {
        $user = User::factory()->vendor()->create([
            'name' => $contactPerson,
            'email' => $email,
            'password' => 'password',
        ]);

        VendorProfile::factory()->create([
            'user_id' => $user->id,
            'company_name' => $companyName,
            'contact_person' => $contactPerson,
            'phone' => $phone,
            'notify_email' => $email,
            'status' => VendorStatus::Active,
        ]);
    }

    private function buyer(
        string $companyName,
        string $contactPerson,
        string $email,
        string $phone,
        string $destinationCountry,
        string $yard,
        bool $unverified = false,
        bool $pending = false,
    ): void {
        $factory = User::factory()->buyer();

        if ($unverified) {
            $factory = $factory->unverified();
        }

        $user = $factory->create([
            'name' => $contactPerson,
            'email' => $email,
            'password' => 'password',
        ]);

        $profileFactory = BuyerProfile::factory();

        if ($pending) {
            $profileFactory = $profileFactory->pending();
        }

        // firstOrCreate, not a hard dependency on CountrySeeder's own
        // placeholder list -- this demo cast intentionally uses a wider
        // variety of countries than the real starting list (Australia, New
        // Zealand) to show a realistic international buyer spread.
        $country = Country::query()->firstOrCreate(['name' => $destinationCountry]);

        $profileFactory->create([
            'user_id' => $user->id,
            'company_name' => $companyName,
            'member_code' => BuyerProfile::generateMemberCode($user),
            'country_id' => $country->id,
            'default_yard' => $yard,
            'phone' => $phone,
        ]);
    }
}
