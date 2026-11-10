<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Network\Genealogy;
use App\Services\Network\Tree;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The member's team: their binary tree and their PV log (specification section 3). */
class TeamController extends Controller
{
    public function tree(Request $request, Genealogy $genealogy, Tree $tree): Response
    {
        $me = $request->user();
        $position = $me->position;

        if ($position === null) {
            return Inertia::render('team/tree', ['placed' => false, 'tree' => null, 'me' => $me->id, 'pv' => null]);
        }

        // A member can look down their own tree, never above it or into someone else's.
        $root = $me;
        if ($request->filled('root')) {
            $candidate = User::find((int) $request->input('root'));
            abort_unless($candidate && $tree->isInTreeOf($candidate->id, $me->id), 404);
            $root = $candidate;
        }

        return Inertia::render('team/tree', [
            'placed' => true,
            'tree' => $genealogy->subtree($root),
            'me' => $me->id,
            'pv' => $genealogy->pvFor([$me->id])[$me->id] ?? null,
            // One level up, never above the member themselves.
            'up' => $root->id === $me->id ? null : $root->position->parent_id,
        ]);
    }

    public function pv(Request $request, Genealogy $genealogy): Response
    {
        $me = $request->user();

        return Inertia::render('team/pv', [
            'pv' => $genealogy->pvFor([$me->id])[$me->id] ?? null,
            'log' => $genealogy->pvLog($me),
        ]);
    }
}
