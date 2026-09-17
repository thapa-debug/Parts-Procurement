<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_weight_brackets', function (Blueprint $table) {
            // Settings::renormalizeBracketOrder() rewrites every bracket's
            // `order` sequentially, one row at a time -- a row's new value
            // can transiently match another row's not-yet-updated old
            // value mid-pass (e.g. inserting a new lowest-weight bracket
            // shifts every existing one up by one), which a UNIQUE
            // constraint rejects immediately even though the end state is
            // fine. `order` is purely an internal sort key the app itself
            // fully manages (never user-entered), so nothing relies on the
            // DB enforcing its uniqueness -- dropped in favour of a plain
            // index for the ORDER BY.
            $table->dropUnique(['order']);
            $table->index('order');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_weight_brackets', function (Blueprint $table) {
            $table->dropIndex(['order']);
            $table->unique('order');
        });
    }
};
