<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycSubmission;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin home page. For now: members and KYC at a glance. Money, orders,
 * stockists, pins and pools join as those parts are built.
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => [
                'members' => User::count(),
                'activeMembers' => User::whereNotNull('activated_at')->count(),
                'bannedMembers' => User::where('status', User::STATUS_BANNED)->count(),
                'kycPending' => KycSubmission::where('status', KycSubmission::PENDING)->count(),
            ],
        ]);
    }
}
