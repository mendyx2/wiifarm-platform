<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger (specification section 1: "the ledger is the truth"). Double
 * entry: every transaction is a set of entries that add up to zero, so money
 * is only ever moved, never created or lost. No table holds a balance: a
 * balance is the sum of an account's entries. Transactions and entries are
 * written once and never changed or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            // "member:{id}" or "system": with user_id below, one account per holder and kind.
            $table->string('holder', 40);
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('kind', 40);
            $table->string('currency', 3); // NGN (kobo) or PV (hundredths)
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['holder', 'kind']);
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 60);
            // What triggered it. Unique, so the same trigger can never post twice.
            $table->string('idempotency_key', 191)->unique();
            // A fingerprint of the entries, to tell a harmless repeat from a conflicting one.
            $table->string('fingerprint', 64);
            $table->string('memo')->nullable();
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->bigInteger('amount'); // credit is positive, debit negative
            $table->timestamp('created_at')->useCurrent();

            $table->index(['account_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
    }
};
