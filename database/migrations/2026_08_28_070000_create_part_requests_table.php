<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('buyer_profiles')->restrictOnDelete();

            // Nullable despite always being set in practice (generated from
            // the row's own id post-insert, see PartRequest::
            // generateRequestCode) -- a NOT NULL unique column would need a
            // placeholder value between insert and that follow-up update,
            // and a shared placeholder (e.g. '') collides under concurrent
            // submissions since MySQL treats it as any other unique value.
            // Multiple NULLs don't collide against a unique index, so this
            // sidesteps the race instead of needing a lock.
            $table->string('request_code')->nullable()->unique();
            $table->enum('part_type', ['used', 'new', 'both']);
            $table->string('maker');
            $table->string('car_model');
            $table->string('vin')->nullable();

            // Year and month only, as a plain string (e.g. "2005/10") --
            // never a real date column. Nobody submitting this form knows
            // the exact day a car was manufactured, and a DATE column would
            // force fabricating one (misleading precision we don't have).
            $table->string('mfg_date', 7)->nullable();

            $table->string('oem_part_number')->nullable();
            $table->string('part_name');
            $table->string('reference_url', 2048)->nullable();
            $table->text('memo')->nullable();
            $table->enum('status', [
                'new', 'vendor_inquiry', 'quoted', 'paid',
                'ordered_to_vendor', 'procurement_failed', 'shipped', 'received',
            ])->default('new');

            // Snapshot pricing (CLAUDE.md §6.2) -- set once, when the admin
            // presents a quote. Never recomputed from live settings after
            // that, so historical orders keep showing what the buyer
            // actually paid even if the margin rate changes later.
            $table->unsignedSmallInteger('applied_rate')->nullable();
            $table->unsignedInteger('applied_min_fee')->nullable();
            $table->unsignedInteger('buyer_price')->nullable();

            $table->enum('shipping_method', ['dhl', 'vehicle', 'container'])->nullable();
            $table->unsignedInteger('shipping_fee')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('part_requests');
    }
};
