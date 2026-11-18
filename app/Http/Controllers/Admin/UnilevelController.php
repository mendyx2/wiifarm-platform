<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compensation\UnilevelPayout;
use App\Models\Compensation\UnilevelRun;
use Inertia\Inertia;
use Inertia\Response;

/** The monthly unilevel runs and what each member earned, by generation (specification section 5.10). */
class UnilevelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/unilevel/index', [
            'rates' => config('compensation.unilevel'),
            'runs' => UnilevelRun::query()->orderByDesc('month')->paginate(24)->through(fn (UnilevelRun $r) => [
                'id' => $r->id, 'month' => $r->month, 'status' => $r->status, 'activity_required' => $r->activity_required,
                'repurchase_pv' => $r->repurchase_pv, 'earners' => $r->earners, 'amount' => $r->amount, 'unpaid' => $r->unpaid, 'held' => $r->stillHeld(),
            ]),
        ]);
    }

    public function show(UnilevelRun $run): Response
    {
        return Inertia::render('admin/unilevel/show', [
            'run' => [
                'id' => $run->id, 'month' => $run->month, 'activity_required' => $run->activity_required, 'repurchase_pv' => $run->repurchase_pv,
                'earners' => $run->earners, 'amount' => $run->amount, 'unpaid' => $run->unpaid, 'held' => $run->stillHeld(),
            ],
            'lines' => $run->lines()->with(['run:id,month', 'user:id,username,first_name,last_name,package_id', 'user.package:id,name'])
                ->orderByDesc('amount')->orderBy('id')->paginate(50)
                ->through(fn (UnilevelPayout $l) => [
                    'id' => $l->id, 'amount' => $l->amount, 'status' => $l->currentStatus(),
                    'by_generation' => collect($l->by_generation)->map(fn ($v, $g) => ['generation' => (int) $g, 'amount' => (int) $v])->values(),
                    'member' => ['id' => $l->user->id, 'username' => $l->user->username, 'name' => trim($l->user->first_name.' '.$l->user->last_name), 'package' => $l->user->package?->name],
                ]),
        ]);
    }
}
