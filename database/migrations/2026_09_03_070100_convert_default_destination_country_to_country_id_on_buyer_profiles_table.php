<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_profiles', function (Blueprint $table) {
            $table->dropColumn('default_destination_country');
            $table->foreignId('country_id')->after('member_code')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('buyer_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_id');
            $table->string('default_destination_country')->after('member_code');
        });
    }
};
