<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete, not cascade -- payment data must never be
            // destroyed (CLAUDE.md §6.4/§10), even if the underlying
            // part_request is hard-deleted. Same reasoning as
            // presented_quotes' own foreign key.
            $table->foreignId('part_request_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('amount'); // yen, integer -- no decimals (CLAUDE.md §7)
            $table->char('currency', 3)->default('JPY');
            // Plain string, not a native DB enum -- so a later `refunded`
            // case (CLAUDE.md §6.4/§14) needs no schema migration, only a
            // new PaymentStatus case. Cast to PaymentStatus on the model.
            $table->string('status')->default('pending');
            // Which gateway processed this payment (e.g. "stub", "stripe")
            // -- attribution, not itself the confirmation signal.
            $table->string('gateway');
            $table->string('gateway_reference')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
