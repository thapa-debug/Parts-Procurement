<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The company's own receiving locations (office, yard, ...) that a
        // vendor delivers a purchased part TO -- admin-managed from
        // Settings. Distinct from buyer_addresses, which is where the
        // company later ships the part on to the buyer.
        //
        // No is_active flag, unlike countries/makers: a real delete is safe
        // because part_requests snapshots the chosen address's fields at
        // order-confirmation time (receiving_* columns) and keeps only a
        // nullOnDelete fk back to this row -- same reasoning as
        // buyer_addresses.
        Schema::create('company_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique(); // e.g. "Head office", "Chiba yard"
            $table->string('recipient_name')->nullable(); // receiving contact, if any
            $table->string('phone');
            $table->string('postal_code');
            // Japan-only (every receiving location is the company's own),
            // so a prefecture instead of a country_id/state pair.
            $table->string('prefecture');
            $table->string('city');
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_addresses');
    }
};
