<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The unilevel bonus (specification section 5.10): one run a month, and one
 * line per member paid in it, with what each generation brought. A member
 * appears in a month's run once only, so a run started again never pays twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unilevel_runs', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->unique();          // YYYY-MM (Lagos time)
            $table->string('status', 12);                   // running, done
            $table->boolean('activity_required');           // the 10 PV rule in force that month (decision 4)
            $table->unsignedBigInteger('repurchase_pv')->default(0); // all members' repurchase PV that month, hundredths
            $table->unsignedInteger('earners')->default(0);
            $table->unsignedBigInteger('amount')->default(0);   // kobo earned, paid or held
            $table->unsignedBigInteger('unpaid')->default(0);   // kobo of shares nobody could receive
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('unilevel_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unilevel_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->json('by_generation');                  // generation => kobo
            $table->unsignedBigInteger('amount');
            $table->string('status', 8);                    // paid, held
            $table->timestamps();

            $table->unique(['unilevel_run_id', 'user_id']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unilevel_payouts');
        Schema::dropIfExists('unilevel_runs');
    }
};
