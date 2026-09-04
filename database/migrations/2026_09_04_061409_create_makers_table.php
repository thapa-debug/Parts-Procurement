<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('makers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Plain boolean, matching countries -- no third state, just
            // "should this appear in the dropdown".
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('makers');
    }
};
