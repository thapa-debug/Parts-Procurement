<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            // CLAUDE.md §7 lists these on part_requests from the start, but
            // they couldn't be added there: selected_response_id references
            // vendor_responses, a table that didn't exist until Phase 2's
            // vendor-response slice (migrated later than this one). Added
            // now, in the slice that actually needs them.
            $table->unsignedInteger('cost_price')->nullable()->after('status');
            $table->foreignId('selected_response_id')->nullable()->after('cost_price')
                ->constrained('vendor_responses')->nullOnDelete();
            $table->foreignId('confirmed_vendor_id')->nullable()->after('selected_response_id')
                ->constrained('vendor_profiles')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_response_id');
            $table->dropConstrainedForeignId('confirmed_vendor_id');
            $table->dropColumn(['cost_price', 'deleted_at']);
        });
    }
};
