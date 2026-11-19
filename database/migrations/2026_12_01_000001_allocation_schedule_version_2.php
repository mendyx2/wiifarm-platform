<?php

use App\Models\Pools\AllocationSchedule;
use Illuminate\Database\Migrations\Migration;

/**
 * Version 2 of the allocation schedule (Revised Baseline Rules V2, owner,
 * 19 November 2026): Pool A's indirect referral line is 17.99% of the net,
 * not 18.00%; the 0.01% goes to Pool A's contingency fund. It applies from
 * now on; splits already made keep the version they used.
 *
 * A new installation has no schedule yet when this runs; it starts from the
 * configuration, which already holds these rates.
 */
return new class extends Migration
{
    public function up(): void
    {
        $current = AllocationSchedule::orderByDesc('version')->first();
        if ($current === null || $current->version >= 2) {
            return;
        }

        $rates = $current->rates;
        $rates['registration']['net']['A.indirect'] = '17.99';
        $rates['registration']['net']['A.contingency'] = '0.01';

        AllocationSchedule::create([
            'version' => $current->version + 1,
            'rates' => $rates,
            'source' => 'Revised Baseline Rules V2 (owner, 19 November 2026): indirect 17.99%',
            'effective_from' => now(),
        ]);
    }

    public function down(): void
    {
        // A schedule version is never removed: splits made with it name it.
    }
};
