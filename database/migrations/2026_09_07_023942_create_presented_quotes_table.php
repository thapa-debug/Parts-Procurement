<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presented_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_request_id')->constrained()->cascadeOnDelete();
            // Unique, not composite with part_request_id -- a vendor_response
            // already belongs to exactly one part_request via its own FK, so
            // "currently presented" is a fact about the response itself: it
            // can never be an active offer twice at once, on this request or
            // any other. restrictOnDelete, not cascade -- a presented_quotes
            // row is a financial-adjacent snapshot (CLAUDE.md §6.2 applied
            // per-quote); it should never silently disappear because someone
            // deleted the underlying response.
            $table->foreignId('vendor_response_id')->unique()->constrained()->restrictOnDelete();
            // Snapshotted at present-time via PricingService, same shape as
            // part_requests' own snapshot columns (§6.2) -- never recomputed
            // afterward, so a later margin-rate change can't drift what a
            // buyer was already shown for this specific option.
            $table->unsignedInteger('cost_price');
            $table->unsignedSmallInteger('applied_rate');
            $table->unsignedInteger('applied_min_fee');
            $table->unsignedInteger('buyer_price');
            $table->timestamp('presented_at');
            $table->timestamps();
        });

        // Backfill (client revision: main already has Phase 2's single-quote
        // data). Every existing quoted-or-beyond request already has exactly
        // one selected_response_id -- under the new model that's "one quote
        // was presented and it happens to be the one selected", so each
        // becomes one presented_quotes row carrying the same snapshot.
        // part_requests' own selected_response_id/snapshot columns are left
        // exactly as they are; nothing about existing data changes.
        DB::table('part_requests')
            ->whereNotNull('selected_response_id')
            ->get(['id', 'selected_response_id', 'cost_price', 'applied_rate', 'applied_min_fee', 'buyer_price', 'created_at', 'updated_at'])
            ->each(function ($request): void {
                DB::table('presented_quotes')->insert([
                    'part_request_id' => $request->id,
                    'vendor_response_id' => $request->selected_response_id,
                    'cost_price' => $request->cost_price,
                    'applied_rate' => $request->applied_rate,
                    'applied_min_fee' => $request->applied_min_fee,
                    'buyer_price' => $request->buyer_price,
                    // No exact original "presented at" timestamp was ever
                    // captured -- updated_at is the nearest proxy. Display/
                    // audit metadata only, never used in pricing logic.
                    'presented_at' => $request->updated_at,
                    'created_at' => $request->created_at,
                    'updated_at' => $request->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('presented_quotes');
    }
};
