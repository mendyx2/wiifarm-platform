<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deposits by a manual method such as a bank transfer (specification
 * section 6): the member pays outside the platform, uploads proof, and an
 * admin approves or refuses. Proof files are private.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->text('instructions');                  // where to pay, shown to the member
            $table->unsignedBigInteger('min_amount');      // kobo
            $table->unsignedBigInteger('max_amount');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('manual_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('method_id')->constrained('deposit_methods')->restrictOnDelete();
            $table->unsignedBigInteger('amount');          // kobo
            $table->string('reference', 120);              // the member's payment reference
            $table->string('proof_path');                  // private
            $table->string('status', 10)->default('pending'); // pending, approved, rejected
            $table->string('form_id', 36)->unique();
            $table->string('reject_reason')->nullable();
            $table->foreignId('decided_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'id']);
            $table->index(['user_id', 'id']);
        });

        // Switched off until an admin enters Wiifarm's bank details.
        DB::table('deposit_methods')->insert([
            'name' => 'Bank transfer',
            'instructions' => "Pay into:\nBank: (to be set by the admin)\nAccount number: (to be set)\nAccount name: (to be set)\nUse your username as the payment narration.",
            'min_amount' => 100_000, 'max_amount' => 500_000_000,
            'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_deposits');
        Schema::dropIfExists('deposit_methods');
    }
};
