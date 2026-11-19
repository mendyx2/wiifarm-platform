<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What happened to each month's held autoship share (specification 5.6):
 * released to the member once they made the month's autoship, or kept by
 * the cooperative when the first week of the next month ended without it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autoship_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('month', 7);                    // YYYY-MM the held share belongs to
            $table->unsignedBigInteger('amount');          // kobo
            $table->string('outcome', 10);                 // released, kept
            $table->foreignId('transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autoship_settlements');
    }
};
