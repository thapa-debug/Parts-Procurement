<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_vendor', function (Blueprint $table) {
            $table->id();

            // Child data of the request -- cascades if the request is ever
            // deleted. vendor_id restricts instead (mirrors part_requests.
            // buyer_id): never destroy a vendor's invitation history.
            $table->foreignId('part_request_id')->constrained('part_requests')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendor_profiles')->restrictOnDelete();

            // No separate created_at/updated_at (CLAUDE.md §7 lists only
            // this column) -- invited_at already is the row's one and only
            // meaningful timestamp.
            $table->timestamp('invited_at');

            $table->unique(['part_request_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_vendor');
    }
};
