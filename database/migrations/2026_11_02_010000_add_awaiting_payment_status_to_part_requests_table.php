<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Adds `awaiting_payment` to part_requests.status -- a native MySQL ENUM
     * (create_part_requests_table), so the new App\Enums\RequestStatus case
     * needs the column's allowed values widened too, or MySQL rejects it
     * with "Data truncated for column 'status'" the first time
     * CheckoutAction writes it (see CONVENTIONS.md's native-enum note).
     *
     * Then corrects requests that read `paid` with no confirmed payment
     * behind them -- the old CheckoutAction set `paid` the moment a Stripe
     * PaymentIntent was created. Those are really `awaiting_payment`.
     * Requests with a genuinely confirmed payment (stub, Stripe webhook,
     * or a free order's ¥0 confirmation) are untouched.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE part_requests MODIFY status ENUM('new', 'vendor_inquiry', 'quoted', 'awaiting_payment', 'paid', 'ordered_to_vendor', 'procurement_failed', 'shipped', 'received') NOT NULL DEFAULT 'new'");
        }

        DB::table('part_requests')
            ->where('status', 'paid')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('payments')
                    ->whereColumn('payments.part_request_id', 'part_requests.id')
                    ->where('payments.status', 'confirmed');
            })
            ->update(['status' => 'awaiting_payment']);
    }

    public function down(): void
    {
        // Back to the old meaning (awaiting_payment folded into paid)
        // before narrowing, or the narrowing ALTER would reject those rows.
        DB::table('part_requests')->where('status', 'awaiting_payment')->update(['status' => 'paid']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE part_requests MODIFY status ENUM('new', 'vendor_inquiry', 'quoted', 'paid', 'ordered_to_vendor', 'procurement_failed', 'shipped', 'received') NOT NULL DEFAULT 'new'");
        }
    }
};
