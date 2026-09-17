<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            // Copied from the selected presented_quotes row by
            // SelectQuoteAction, the same way buyer_price/shipping_fee
            // already are (CLAUDE.md §14 Phase 4, 無償 flow). Lets
            // CheckoutAction/ConfirmFreeOrderAction each guard that they're
            // never reachable for the other's kind of request, without
            // re-deriving "is this free" from buyer_price === 0 (which
            // would be an implicit, easy-to-break proxy).
            $table->boolean('is_free')->default(false)->after('shipping_fee');
        });
    }

    public function down(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            $table->dropColumn('is_free');
        });
    }
};
