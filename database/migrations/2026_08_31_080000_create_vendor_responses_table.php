<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_request_id')->constrained('part_requests')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendor_profiles')->restrictOnDelete();
            // Nullable, unlike CLAUDE.md §7's literal column list -- a
            // "no stock" reply (is_no_stock) has no cost/rank/lead time to
            // give, only an optional comment.
            $table->unsignedInteger('cost_price')->nullable();
            $table->string('quality_rank')->nullable();
            $table->string('lead_time')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_no_stock')->default(false);
            $table->timestamps();

            // One response per vendor per request -- no revision flow in
            // this slice (not specified; the pending/responded split needs
            // exactly this shape). SubmitVendorResponseAction enforces the
            // same rule again before writing.
            $table->unique(['part_request_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_responses');
    }
};
