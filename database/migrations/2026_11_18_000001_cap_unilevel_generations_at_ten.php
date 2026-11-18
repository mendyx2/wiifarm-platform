<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The board's November 2026 decision: unilevel goes to 10 generations, never
 * more (specification sections 4 and 5.10). Existing packages that were set
 * up with 12, 13 or 15 generations are brought down to 10.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('packages')->where('unilevel_generations', '>', 10)->update(['unilevel_generations' => 10, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // The old depths came from the plan and are not restored.
    }
};
