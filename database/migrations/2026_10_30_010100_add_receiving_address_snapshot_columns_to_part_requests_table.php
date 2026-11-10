<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            // The company receiving address the admin chose for the vendor
            // to deliver this part to, snapshotted by
            // ConfirmOrderToVendorAction (CLAUDE.md §14 Phase 4 slice 6) --
            // the same discipline as the buyer's shipping_* snapshot: once
            // confirmed, a later edit or delete of the CompanyAddress must
            // never change where this order told the vendor to deliver.
            // receiving_address_id is traceability only (nullOnDelete);
            // the flattened columns are the source of truth.
            $table->foreignId('receiving_address_id')->nullable()->after('shipping_address_line2')
                ->constrained('company_addresses')->nullOnDelete();
            $table->string('receiving_label')->nullable()->after('receiving_address_id');
            $table->string('receiving_recipient_name')->nullable()->after('receiving_label');
            $table->string('receiving_phone')->nullable()->after('receiving_recipient_name');
            $table->string('receiving_postal_code')->nullable()->after('receiving_phone');
            $table->string('receiving_prefecture')->nullable()->after('receiving_postal_code');
            $table->string('receiving_city')->nullable()->after('receiving_prefecture');
            $table->string('receiving_address_line1')->nullable()->after('receiving_city');
            $table->string('receiving_address_line2')->nullable()->after('receiving_address_line1');
        });
    }

    public function down(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receiving_address_id');
            $table->dropColumn([
                'receiving_label', 'receiving_recipient_name', 'receiving_phone',
                'receiving_postal_code', 'receiving_prefecture', 'receiving_city',
                'receiving_address_line1', 'receiving_address_line2',
            ]);
        });
    }
};
