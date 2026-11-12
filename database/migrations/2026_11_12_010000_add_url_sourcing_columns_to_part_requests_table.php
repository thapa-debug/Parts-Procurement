<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reference-URL (admin-sourced) flow: the admin sources a request
     * directly from the buyer's own reference_url instead of broadcasting
     * it to vendors (SetUrlPriceAction / MarkPurchasedFromUrlAction).
     *
     * The price snapshot itself reuses the existing cost_price /
     * applied_rate / applied_min_fee / buyer_price / shipping_fee columns
     * (CLAUDE.md §6.2), so checkout, payments and the dashboard read a URL
     * order exactly like a vendor order. Only what has no vendor-flow home
     * is added here:
     * - is_url_sourced: which front half the request went through.
     * - url_weight_kg: the weight the admin entered (there is no vendor
     *   response to carry one) -- what the shipping bracket and DHL read.
     * - url_shipping_fee (+ override flag/reason): the Regular fee
     *   snapshotted at pricing time. Kept apart from shipping_fee because
     *   checkout overwrites shipping_fee with the buyer's chosen method's
     *   fee (and a cancelled DHL checkout doesn't restore it) -- the vendor
     *   flow keeps its Regular fee on presented_quotes for the same reason.
     *   The override reason is admin-internal, never shown to the buyer.
     * - url_purchase_reference: the admin's optional note/reference
     *   (order number etc.) when marking the part purchased.
     */
    public function up(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            $table->boolean('is_url_sourced')->default(false)->after('is_free');
            $table->decimal('url_weight_kg', 8, 2)->nullable()->after('is_url_sourced');
            $table->unsignedInteger('url_shipping_fee')->nullable()->after('url_weight_kg');
            $table->boolean('url_shipping_fee_overridden')->default(false)->after('url_shipping_fee');
            $table->text('url_shipping_fee_override_reason')->nullable()->after('url_shipping_fee_overridden');
            $table->text('url_purchase_reference')->nullable()->after('url_shipping_fee_override_reason');
        });
    }

    public function down(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            $table->dropColumn([
                'is_url_sourced',
                'url_weight_kg',
                'url_shipping_fee',
                'url_shipping_fee_overridden',
                'url_shipping_fee_override_reason',
                'url_purchase_reference',
            ]);
        });
    }
};
