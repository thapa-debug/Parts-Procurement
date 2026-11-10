<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            // App\Enums\DeliveryMethod -- a fixed per-vendor property. Defaults
            // to 'both' (the least restrictive value) so existing vendors stay
            // usable by either delivery path until the admin sets the real one.
            $table->enum('delivery_method', ['carry', 'deliver', 'both'])->default('both')->after('notify_email');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->dropColumn('delivery_method');
        });
    }
};
