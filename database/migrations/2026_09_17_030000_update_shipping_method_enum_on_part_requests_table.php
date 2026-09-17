<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * part_requests.shipping_method is a native MySQL ENUM (from the
     * original create_part_requests_table migration), not just the PHP
     * ShippingMethod enum cast -- so App\Enums\ShippingMethod dropping
     * Vehicle/Container in favour of Standard (CLAUDE.md §14 Phase 4:
     * rule-based shipping v1) needs the DB column's own allowed values
     * updated too, or MySQL rejects the new string with "Data truncated
     * for column 'shipping_method'" (strict mode) the moment
     * SelectQuoteAction tries to write 'standard'. Invisible against
     * Pest's sqlite connection -- sqlite's ENUM support doesn't enforce
     * this the same way -- only surfaced against real MySQL.
     */
    public function up(): void
    {
        // MySQL-only: shipping_method is a native ENUM there, so the DB
        // column's own allowed values need updating alongside the PHP
        // enum, or MySQL rejects 'standard' with "data truncated" (strict
        // mode) the moment SelectQuoteAction tries to write it. Sqlite
        // (the test connection) has no real ENUM constraint to begin with
        // -- ALTER ... MODIFY isn't even valid syntax there -- so this is
        // a genuine no-op on that driver, not skipped test coverage.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Widen first (union of old and new values), so any existing row
        // still carrying the old vehicle/container values (pre-launch
        // dev/test data only -- there is no real client data yet) can
        // actually be updated to 'standard' before the column's allowed
        // values are narrowed to their final set. Doing the narrowing
        // ALTER first would reject that same UPDATE with the identical
        // "data truncated" error this migration exists to fix.
        DB::statement("ALTER TABLE part_requests MODIFY shipping_method ENUM('dhl', 'vehicle', 'container', 'standard') NULL");
        DB::table('part_requests')->whereIn('shipping_method', ['vehicle', 'container'])->update(['shipping_method' => 'standard']);
        DB::statement("ALTER TABLE part_requests MODIFY shipping_method ENUM('standard', 'dhl') NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE part_requests MODIFY shipping_method ENUM('dhl', 'vehicle', 'container') NULL");
    }
};
