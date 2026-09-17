<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presented_quotes', function (Blueprint $table) {
            // Snapshotted via ShippingCalculator at present-time, same
            // discipline as the pricing snapshot above -- never recomputed
            // afterward if shipping_weight_brackets changes later (CLAUDE.md
            // §14 Phase 4: rule-based shipping v1).
            $table->unsignedInteger('shipping_fee')->after('buyer_price');
            // Whether the admin overrode ShippingCalculator's own figure at
            // presentation time, and why -- required whenever overridden
            // (PresentQuoteAction enforces this), logged via this model's
            // existing LogsActivity. Admin-internal only, never shown to
            // the buyer.
            $table->boolean('shipping_fee_overridden')->default(false)->after('shipping_fee');
            $table->text('shipping_fee_override_reason')->nullable()->after('shipping_fee_overridden');
        });
    }

    public function down(): void
    {
        Schema::table('presented_quotes', function (Blueprint $table) {
            $table->dropColumn(['shipping_fee', 'shipping_fee_overridden', 'shipping_fee_override_reason']);
        });
    }
};
