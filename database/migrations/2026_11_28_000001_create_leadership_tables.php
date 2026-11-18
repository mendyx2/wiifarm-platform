<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The leadership bonus (specification section 5.7): one run a month, and one
 * line per leader paid in it. A leader appears in a month's run once only,
 * so a run started again never pays twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leadership_runs', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->unique();        // YYYY-MM, the month paid for (Lagos time)
            $table->string('status', 12);                 // running, done
            $table->string('team_rule', 40);              // how "team" was counted that month, for the record
            $table->unsignedInteger('leaders')->default(0);
            $table->unsignedBigInteger('amount')->default(0);   // kobo earned, paid or held
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leadership_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leadership_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('repurchase_pv');   // the leader's repurchase PV at month end, hundredths
            $table->unsignedInteger('team_members');       // members counted in the team
            $table->unsignedBigInteger('team_matching');   // kobo the team earned from matching that month
            $table->unsignedBigInteger('amount');          // kobo: 5% of it
            $table->string('status', 8);                   // paid, held, none (nothing to pay)
            $table->timestamps();

            $table->unique(['leadership_run_id', 'user_id']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leadership_payouts');
        Schema::dropIfExists('leadership_runs');
    }
};
