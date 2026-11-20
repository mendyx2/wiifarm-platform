<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * USDT (TRC20) deposits to Wiifarm's one address (specification section 6).
 * Each deposit asks for an amount no other open deposit asks for, so a
 * payment on the chain fits one deposit only. Every payment seen on the
 * chain is recorded once (a transaction ID can never be credited twice).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usdt_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');            // kobo, what the main balance gets
            $table->unsignedBigInteger('rate_kobo');         // kobo paid for 1 USDT when it was started
            $table->unsignedBigInteger('units');             // USDT asked, in millionths (1 USDT = 1,000,000)
            $table->string('address', 34);                   // Wiifarm's address shown to the member
            $table->string('status', 10)->default('waiting'); // waiting, paid, expired, review, rejected
            $table->timestamp('expires_at');
            $table->string('txid', 64)->nullable()->unique();
            $table->unsignedBigInteger('received_units')->nullable();
            $table->string('sender', 34)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('credited')->nullable(); // kobo actually added
            $table->string('note')->nullable();
            $table->foreignId('decided_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['address', 'units']);
            $table->index(['user_id', 'id']);
        });

        Schema::create('usdt_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('txid', 64)->unique();
            $table->string('address', 34);
            $table->string('sender', 34)->nullable();
            $table->unsignedBigInteger('units');
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 10);                    // credited, review, unmatched
            $table->foreignId('usdt_deposit_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usdt_transfers');
        Schema::dropIfExists('usdt_deposits');
    }
};
