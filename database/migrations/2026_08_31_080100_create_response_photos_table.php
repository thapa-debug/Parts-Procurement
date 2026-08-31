<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('response_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_response_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->unsignedInteger('size');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('response_photos');
    }
};
