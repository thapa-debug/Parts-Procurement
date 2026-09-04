<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            $table->dropColumn('maker');
            $table->foreignId('maker_id')->after('part_type')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('part_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maker_id');
            $table->string('maker')->after('part_type');
        });
    }
};
