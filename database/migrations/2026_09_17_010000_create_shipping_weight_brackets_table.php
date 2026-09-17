<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_weight_brackets', function (Blueprint $table) {
            $table->id();
            // Null on exactly one row -- the top/catch-all bracket, "and
            // above" (CLAUDE.md §14 Phase 4: rule-based shipping v1,
            // extending to heavy items like engines). ShippingCalculator
            // picks the smallest bracket whose upper_kg covers the given
            // weight, ordered by `order` -- enforced there, not by a DB
            // constraint.
            $table->decimal('upper_kg', 8, 2)->nullable();
            $table->unsignedInteger('fee'); // yen
            $table->unsignedInteger('order')->unique();
            $table->timestamps();
        });

        // Sensible starting brackets, admin-editable later via a Settings
        // UI slice (not yet built). Seeded directly in the migration --
        // not only via ShippingWeightBracketSeeder -- so the table is never
        // empty from the moment it exists, the same way PricingService's
        // Setting::get() defaults never leave margin calculation unconfigured.
        DB::table('shipping_weight_brackets')->insert([
            ['upper_kg' => 5, 'fee' => 3_000, 'order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['upper_kg' => 10, 'fee' => 5_000, 'order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['upper_kg' => 20, 'fee' => 8_000, 'order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['upper_kg' => 40, 'fee' => 15_000, 'order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['upper_kg' => 80, 'fee' => 25_000, 'order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['upper_kg' => 150, 'fee' => 40_000, 'order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['upper_kg' => 300, 'fee' => 70_000, 'order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['upper_kg' => null, 'fee' => 120_000, 'order' => 8, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_weight_brackets');
    }
};
