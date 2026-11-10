<?php

namespace Tests\Feature\Network;

use App\Models\Package;
use App\Models\User;
use App\Services\Ledger\Ledger;
use App\Services\Network\Registration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Helpers for building a small network in a test. */
abstract class NetworkTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class); // packages and the company account at the top
    }

    protected function package(string $name): Package
    {
        return Package::where('name', $name)->firstOrFail();
    }

    /** A new member who signed up under $sponsor and paid $package. */
    protected function join(string $username, ?User $sponsor = null, string $package = 'Pre Starter', ?User $parent = null, ?string $leg = null): User
    {
        $member = User::factory()->create([
            'username' => $username,
            'sponsor_id' => ($sponsor ?? User::company())->id,
        ]);
        $member->forceFill(['requested_parent_id' => $parent?->id, 'requested_leg' => $leg])->save();

        app(Registration::class)->activate($member, $this->package($package), 'test:'.$username);

        return $member->fresh();
    }

    /** [parent username, leg] of a member. @return array{0: ?string, 1: ?string} */
    protected function placeOf(User $member): array
    {
        $position = $member->position()->with('parent')->first();

        return [$position?->parent?->username, $position?->leg];
    }

    /** Whole PV on a member's leg. */
    protected function pv(User $member, string $leg, string $type = 'pairing'): float
    {
        return app(Ledger::class)->pvBalances($member)[$leg][$type] / 100;
    }
}
