<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The owner's decision of 19 November 2026, from the compensation plan
 * document "Wiifarm Coop Binary System": the unilevel bonus goes to 15
 * generations (the indirect referral bonus stays at 10). Each package reaches
 * the depth the plan's unilevel table gives it. This undoes the earlier cap at
 * 10 (migration 2026_11_18_000001).
 */
return new class extends Migration
{
    private const DEPTHS = ['Pre Starter' => 4, 'Starter' => 6, 'Builder' => 10, 'Booster' => 10, 'Executive' => 12, 'Advance' => 13, 'Mega' => 15];

    public function up(): void
    {
        foreach (self::DEPTHS as $name => $generations) {
            DB::table('packages')->where('name', $name)->update(['unilevel_generations' => $generations, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('packages')->where('unilevel_generations', '>', 10)->update(['unilevel_generations' => 10, 'updated_at' => now()]);
        DB::table('packages')->where('name', 'Builder')->update(['unilevel_generations' => 8, 'updated_at' => now()]);
    }
};
