<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Payments made online through Paystack (specification sections 2 and 6): a
 * package, an upgrade or a deposit to the main balance. Each has its own
 * reference, which is credited once only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('purpose', 12);                 // package, upgrade, deposit
            $table->foreignId('package_id')->nullable()->constrained()->restrictOnDelete();      // the package bought, or upgraded to
            $table->foreignId('from_package_id')->nullable()->constrained('packages')->restrictOnDelete(); // an upgrade's starting package
            $table->unsignedBigInteger('amount');          // kobo
            $table->string('gateway', 20)->default('paystack');
            $table->string('reference', 40)->unique();
            $table->string('status', 12);                  // pending, paid, failed, mismatch
            $table->string('gateway_status', 30)->nullable();
            $table->string('channel', 30)->nullable();     // card, bank, ussd, ...
            $table->string('note')->nullable();            // what happened, in plain words
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('checked_at')->nullable();   // last asked Paystack
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payments');
    }
};
