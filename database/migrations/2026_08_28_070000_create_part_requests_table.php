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
            $table->string('request_code')->unique();
            $table->enum('part_type', ['used', 'new', 'both']);
            $table->string('maker');
            $table->string('car_model');
            $table->string('vin')->nullable();
            $table->date('mfg_date')->nullable();
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
