<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Services\Network\Genealogy;
use App\Services\Network\Tree;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The admin's view of the network: any member's tree and PV log, and the packages. */
class NetworkController extends Controller
{
    public function tree(Request $request, User $member, Genealogy $genealogy): Response
    {
        abort_unless($member->position()->exists(), 404);

        return Inertia::render('admin/members/tree', [
            'member' => ['id' => $member->id, 'username' => $member->username, 'name' => $member->name],
            'tree' => $genealogy->subtree($member),
            'up' => $member->position->parent_id,
        ]);
    }

    public function pv(User $member, Genealogy $genealogy): Response
    {
        return Inertia::render('admin/members/pv', [
            'member' => ['id' => $member->id, 'username' => $member->username, 'name' => $member->name],
            'pv' => $genealogy->pvFor([$member->id])[$member->id] ?? null,
            'log' => $genealogy->pvLog($member),
        ]);
    }

    public function packages(): Response
    {
        return Inertia::render('admin/packages', [
            'packages' => Package::orderBy('sort')->withCount(['members' => fn ($q) => $q->whereNotNull('activated_at')])->get()
                ->map(fn (Package $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => $p->price,
                    'pv' => $p->pv,
                    'pv_value' => $p->pvValue(),
                    'product_packs' => $p->product_packs,
                    'direct_bonus_percent' => $p->direct_bonus_percent,
                    'match_percent' => $p->match_percent,
                    'per_match' => $p->perMatch(),
                    'daily_cap_matches' => $p->daily_cap_matches,
                    'unilevel_generations' => $p->unilevel_generations,
                    'upgradable' => $p->upgradable,
                    'is_active' => $p->is_active,
                    'members' => $p->members_count,
                ]),
        ]);
    }
}
