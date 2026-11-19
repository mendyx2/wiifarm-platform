<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A leader now qualifies on 480,000 PV on the lesser leg (the owner, after
 * speaking with a leader, 19 November 2026), not on repurchase PV. The column
 * that records the qualifying PV is renamed to say what it holds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leadership_payouts', function (Blueprint $table) {
            $table->renameColumn('repurchase_pv', 'lesser_leg_pv');
        });
    }

    public function down(): void
    {
        Schema::table('leadership_payouts', function (Blueprint $table) {
            $table->renameColumn('lesser_leg_pv', 'repurchase_pv');
        });
    }
};
