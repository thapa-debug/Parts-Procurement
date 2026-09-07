<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_responses', function (Blueprint $table) {
            // Nullable like cost_price/quality_rank/lead_time above -- a "no
            // stock" reply has none of these either. Required only for a
            // real quote, enforced in RequestResponse's Livewire rules(),
            // the same place cost_price/quality_rank/lead_time are.
            // Captured now (client revision) so Phase 4 checkout can
            // calculate admin->buyer shipping cost without having to go
            // back to the vendor for measurements after the fact.
            $table->decimal('weight_kg', 8, 2)->nullable()->after('comment');
            $table->decimal('length_cm', 8, 2)->nullable()->after('weight_kg');
            $table->decimal('width_cm', 8, 2)->nullable()->after('length_cm');
            $table->decimal('height_cm', 8, 2)->nullable()->after('width_cm');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_responses', function (Blueprint $table) {
            $table->dropColumn(['weight_kg', 'length_cm', 'width_cm', 'height_cm']);
        });
    }
};
