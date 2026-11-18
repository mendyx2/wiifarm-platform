<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Withdrawals (specification section 6). The money is in the ledger; these
 * tables hold the methods and each request's details and standing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->unsignedBigInteger('min_amount'); // kobo
            $table->unsignedBigInteger('max_amount');
            $table->unsignedBigInteger('fixed_charge')->default(0);
            $table->decimal('percent_charge', 5, 2)->default(0);
            $table->json('fields'); // [{key, label}], all required
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->string('holder_type', 12); // member, store or affiliate
            $table->unsignedBigInteger('holder_id');
            $table->foreignId('wallet_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->foreignId('method_id')->constrained('withdrawal_methods')->restrictOnDelete();
            $table->unsignedBigInteger('amount');  // leaves the wallet
            $table->unsignedBigInteger('charge');  // to the cooperative
            $table->unsignedBigInteger('payable'); // sent to the person: amount − charge
            $table->json('details');
            $table->string('status', 10)->default('pending'); // pending, paid, rejected
            $table->string('form_id', 36)->unique();
            $table->string('reference', 191)->nullable();
            $table->string('reject_reason')->nullable();
            $table->foreignId('decided_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['holder_type', 'holder_id', 'id']);
            $table->index(['status', 'id']);
        });

        // The first method; an admin sets its limits and charges.
        DB::table('withdrawal_methods')->insert([
            'name' => 'Bank transfer',
            'min_amount' => 100_000,         // ₦1,000
            'max_amount' => 500_000_000,     // ₦5,000,000
            'fixed_charge' => 0,
            'percent_charge' => 0,
            'fields' => json_encode([
                ['key' => 'bank', 'label' => 'Bank name'],
                ['key' => 'account_number', 'label' => 'Account number'],
                ['key' => 'account_name', 'label' => 'Account name'],
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('withdrawal_methods');
    }
};
