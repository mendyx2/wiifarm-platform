<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compensation\LeadershipPayout;
use App\Models\Compensation\LeadershipRun;
use App\Services\Compensation\Leadership;
use Inertia\Inertia;
use Inertia\Response;

/** The monthly leadership runs and what each leader earned (specification section 5.7). */
class LeadershipController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/leadership/index', [
            'rule' => Leadership::teamRule(),
            'qualifyingPv' => config('compensation.leadership.qualifying_pv'),
            'runs' => LeadershipRun::query()->orderByDesc('month')->paginate(24)->through(fn (LeadershipRun $r) => [
                'id' => $r->id, 'month' => $r->month, 'status' => $r->status, 'team_rule' => $r->team_rule,
                'leaders' => $r->leaders, 'amount' => $r->amount, 'held' => $r->stillHeld(),
            ]),
        ]);
    }

    public function show(LeadershipRun $run): Response
    {
        return Inertia::render('admin/leadership/show', [
            'run' => ['id' => $run->id, 'month' => $run->month, 'team_rule' => $run->team_rule, 'leaders' => $run->leaders, 'amount' => $run->amount, 'held' => $run->stillHeld()],
            'lines' => $run->lines()->with(['run:id,month', 'user:id,username,first_name,last_name'])->orderByDesc('amount')->orderBy('id')->paginate(50)
                ->through(fn (LeadershipPayout $l) => [
                    'id' => $l->id, 'repurchase_pv' => $l->repurchase_pv, 'team_members' => $l->team_members,
                    'team_matching' => $l->team_matching, 'amount' => $l->amount, 'status' => $l->currentStatus(),
                    'member' => ['id' => $l->user->id, 'username' => $l->user->username, 'name' => trim($l->user->first_name.' '.$l->user->last_name)],
                ]),
        ]);
    }
}
