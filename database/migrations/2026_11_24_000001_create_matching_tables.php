<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The daily matching bonus (specification section 5.5): one run a day, and
 * one line per member paid in it. A member can appear in a day's run once
 * only, so a run that is started again never pays twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_runs', function (Blueprint $table) {
            $table->id();
            $table->string('day', 10)->unique();    // YYYY-MM-DD: the day the run closes (Lagos time)
            $table->string('status', 12);            // running, done
            $table->unsignedInteger('members')->default(0);
            $table->unsignedInteger('matches')->default(0);
            $table->unsignedBigInteger('amount')->default(0);   // kobo earned, paid or held
            $table->unsignedBigInteger('held')->default(0);     // kobo waiting for the line to refill
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_run_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('left_pv');    // pairing PV before the run, hundredths
            $table->unsignedBigInteger('right_pv');
            $table->unsignedInteger('matches');
            $table->boolean('capped');                // the daily cap stopped more matches
            $table->unsignedBigInteger('amount');     // kobo: matches × the package's "paid per match"
            $table->unsignedBigInteger('autoship');   // of which held in the autoship wallet
            $table->string('status', 8);              // paid, held
            $table->timestamps();

            $table->unique(['match_run_id', 'user_id']);
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
        Schema::dropIfExists('match_runs');
    }
};
