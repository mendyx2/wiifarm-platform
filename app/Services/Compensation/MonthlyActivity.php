<?php

namespace App\Services\Compensation;

use App\Support\Settings;

/**
 * The monthly purchase ("authorship") a member makes to stay active
 * (Revised Baseline Rules: 10.5 PV, ₦10,500, configurable). It decides the
 * autoship hold on matching and, while that switch is on, the unilevel bonus.
 */
class MonthlyActivity
{
    /** The PV a member must buy in a calendar month, in hundredths (10.5 PV = 1050). */
    public static function requiredPv(): int
    {
        return (int) round(((float) Settings::get('activity.monthly_pv')) * 100);
    }
}
