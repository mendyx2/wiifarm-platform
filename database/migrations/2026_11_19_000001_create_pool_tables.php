<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manufacturing partners, the allocation schedule and held payouts
 * (specification section 7). The money itself is in the ledger: each
 * manufacturer and each pool line has its own ledger account.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A separate company from the cooperative, with its own account.
        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // The board's allocation schedule. A new version applies from its date onwards.
        Schema::create('allocation_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->json('rates');
            $table->string('source', 191);
            $table->timestamp('effective_from');
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // A payout a pool line could not cover yet: held, then paid in full, oldest first.
        Schema::create('held_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('pool', 4);
            $table->string('line', 40);
            $table->foreignId('to_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('type', 60);
            $table->string('idempotency_key', 191)->unique();
            $table->string('memo')->nullable();
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('status', 10)->default('held'); // held or paid
            $table->foreignId('transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('paid_at')->nullable();

            $table->index(['pool', 'line', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_payouts');
        Schema::dropIfExists('allocation_schedules');
        Schema::dropIfExists('manufacturers');
    }
};
