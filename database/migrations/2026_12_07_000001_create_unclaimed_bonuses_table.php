<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The Unclaimed Bonus Reserve (the owner, 19 November 2026): every bonus
 * share nobody could receive goes to its pool's "unclaimed" account, not the
 * contingency fund. This table says where each inflow came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unclaimed_bonuses', function (Blueprint $table) {
            $table->id();
            $table->string('pool', 2);
            $table->string('line', 40);                          // the pool line the share came from
            $table->string('bonus', 30);                         // direct, indirect, keyin, upgrade, unilevel, stockist_sponsor, ucb, autoship
            $table->unsignedTinyInteger('generation')->nullable();
            $table->string('reason', 30);                        // no_upline, company, banned, not_paid, package_depth, no_key_in, autoship_missed
            $table->foreignId('beneficiary_user_id')->nullable()->constrained('users')->nullOnDelete();   // who would have received it
            $table->foreignId('source_user_id')->nullable()->constrained('users')->nullOnDelete();        // whose joining, purchase or store it came from
            $table->string('subject_type', 80)->nullable();      // what triggered it (a package change, order, store, award)
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('amount');                // kobo moved into the reserve
            $table->foreignId('transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('memo')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pool', 'id']);
            $table->index(['bonus', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unclaimed_bonuses');
    }
};
