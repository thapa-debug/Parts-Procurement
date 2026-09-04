<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Plain boolean, not an enum like vendor_profiles.status -- no
            // third state and no dedicated suspend/resume verb pair here,
            // just "should this appear in the dropdown" (CONVENTIONS.md-
            // style reasoning: match users.is_active, not vendor_profiles.status).
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
