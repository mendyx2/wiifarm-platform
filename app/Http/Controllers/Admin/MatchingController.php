<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compensation\MatchRun;
use App\Models\Compensation\MemberMatch;
use Inertia\Inertia;
use Inertia\Response;

/** The nightly matching runs and what each member earned (specification section 5.5). */
class MatchingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/matching/index', [
            'runs' => MatchRun::query()->orderByDesc('day')->paginate(31)->through(fn (MatchRun $r) => [
                'id' => $r->id, 'day' => $r->day, 'status' => $r->status, 'members' => $r->members,
                'matches' => $r->matches, 'amount' => $r->amount, 'held' => $r->stillHeld(),
                'finished_at' => $r->finished_at?->toDateTimeString(),
            ]),
        ]);
    }

    public function show(MatchRun $run): Response
    {
        return Inertia::render('admin/matching/show', [
            'run' => [
                'id' => $run->id, 'day' => $run->day, 'status' => $run->status, 'members' => $run->members,
                'matches' => $run->matches, 'amount' => $run->amount, 'held' => $run->stillHeld(),
            ],
            'lines' => $run->lines()->with(['run:id,day', 'user:id,username,first_name,last_name', 'package:id,name'])->orderByDesc('amount')->orderBy('id')
                ->paginate(50)->through(fn (MemberMatch $m) => self::row($m) + [
                    'member' => ['id' => $m->user->id, 'username' => $m->user->username, 'name' => trim($m->user->first_name.' '.$m->user->last_name)],
                ]),
        ]);
    }

    /** @return array<string, mixed> */
    public static function row(MemberMatch $m): array
    {
        return [
            'id' => $m->id, 'package' => $m->package->name,
            'left_pv' => $m->left_pv, 'right_pv' => $m->right_pv, 'matches' => $m->matches, 'capped' => $m->capped,
            'amount' => $m->amount, 'autoship' => $m->autoship, 'status' => $m->currentStatus(),
        ];
    }
}
