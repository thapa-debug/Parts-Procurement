<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyer_addresses', function (Blueprint $table) {
            $table->id();
            // cascadeOnDelete, unlike part_requests' financial-adjacent
            // rows -- an address has no independent value once its buyer
            // profile is gone, and (unlike a payment) nothing here needs to
            // survive that.
            $table->foreignId('buyer_id')->constrained('buyer_profiles')->cascadeOnDelete();
            $table->string('recipient_name');
            $table->string('phone');
            $table->string('postal_code');
            // Same pattern as buyer_profiles.country_id -- active-only
            // enforced at write time by whichever Form Request lands in
            // Slice 3, restrictOnDelete because countries are never hard-
            // deleted anyway (CLAUDE.md §7).
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->string('state')->nullable(); // not every country uses state/province
            $table->string('city');
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            // Exactly one true per buyer once any address exists -- an
            // application-level invariant enforced by the Buyer*Address
            // Actions, not a DB constraint.
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_addresses');
    }
};
