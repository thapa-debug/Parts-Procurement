<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            // The buyer's chosen saved address, snapshotted at checkout
            // (CLAUDE.md §14 Phase 4 slice 2) -- same discipline as the
            // pricing snapshot (§6.2): once written here, a later edit or
            // delete of the source BuyerAddress must never change what this
            // request shows it shipped to.
            //
            // shipping_address_id is kept only for traceability back to the
            // live row while it still exists -- nullOnDelete, not restrict,
            // because a buyer must always be free to delete a saved address
            // (DeleteBuyerAddressAction) even after it's been used on an
            // order. The flattened columns below are the actual source of
            // truth for a placed order, immune to that deletion.
            $table->foreignId('shipping_address_id')->nullable()->after('shipping_fee')
                ->constrained('buyer_addresses')->nullOnDelete();
            $table->string('shipping_recipient_name')->nullable()->after('shipping_address_id');
            $table->string('shipping_phone')->nullable()->after('shipping_recipient_name');
            $table->string('shipping_postal_code')->nullable()->after('shipping_phone');
            // A plain string, not a country_id fk -- unlike the live
            // BuyerAddress row, this snapshot must stay correct even if the
            // Country it was copied from is later renamed or deactivated
            // (same reasoning as the pricing snapshot never re-deriving
            // from live settings).
            $table->string('shipping_country')->nullable()->after('shipping_postal_code');
            $table->string('shipping_state')->nullable()->after('shipping_country');
            $table->string('shipping_city')->nullable()->after('shipping_state');
            $table->string('shipping_address_line1')->nullable()->after('shipping_city');
            $table->string('shipping_address_line2')->nullable()->after('shipping_address_line1');
        });
    }

    public function down(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_address_id');
            $table->dropColumn([
                'shipping_recipient_name', 'shipping_phone', 'shipping_postal_code',
                'shipping_country', 'shipping_state', 'shipping_city',
                'shipping_address_line1', 'shipping_address_line2',
            ]);
        });
    }
};
