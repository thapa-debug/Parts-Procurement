<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presented_quotes', function (Blueprint $table) {
            // 無償 (free) flow (CLAUDE.md §14 Phase 4): an admin-discretionary
            // flag set at presentation time (PresentQuoteAction), never tied
            // to any external system. When true, buyer_price and
            // shipping_fee on this same row are forced to 0 -- cost_price/
            // applied_rate/applied_min_fee are still computed and stored
            // normally, so the admin's own accounting keeps showing the real
            // vendor cost and what the margin would have been.
            $table->boolean('is_free')->default(false)->after('shipping_fee_override_reason');
        });
    }

    public function down(): void
    {
        Schema::table('presented_quotes', function (Blueprint $table) {
            $table->dropColumn('is_free');
        });
    }
};
