<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/*
 * The unilevel bonus is now paid at each repurchase (Revised Baseline Rules;
 * the owner, 19 November 2026), so the monthly run's tables are no longer
 * used. Every unilevel payment is in the ledger, with the order it came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('unilevel_payouts');
        Schema::dropIfExists('unilevel_runs');
    }

    public function down(): void
    {
        // The monthly run is not coming back; its old migration recreates the tables if ever needed.
    }
};
