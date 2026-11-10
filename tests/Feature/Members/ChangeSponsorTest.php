<?php

namespace Tests\Feature\Members;

use App\Models\SponsorChange;
use App\Models\User;
use App\Services\Members\ChangeSponsor;
use Database\Seeders\CompanyAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Changing a member's sponsor (specification section 2): admin only, always logged.
 */
class ChangeSponsorTest extends TestCase
{
    use RefreshDatabase;

    private User $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CompanyAccountSeeder::class);
        $this->company = User::company();
    }

    private function member(?User $sponsor = null): User
    {
        $u = User::factory()->create();
        $u->forceFill(['sponsor_id' => ($sponsor ?? $this->company)->id])->save();

        return $u;
    }

    public function test_a_change_moves_the_member_and_logs_old_and_new_sponsor()
    {
        $a = $this->member();
        $b = $this->member();
        $member = $this->member($a);

        $change = (new ChangeSponsor)($member, $b, 'Registered under the wrong sponsor', adminId: 7);

        $this->assertSame($b->id, $member->fresh()->sponsor_id);
        $this->assertSame($a->id, $change->old_sponsor_id);
        $this->assertSame($b->id, $change->new_sponsor_id);
        $this->assertSame(7, $change->changed_by_admin_id);
        $this->assertSame(1, SponsorChange::count());
    }

    public function test_a_change_that_would_make_a_loop_is_refused()
    {
        $top = $this->member();
        $middle = $this->member($top);
        $bottom = $this->member($middle);

        $this->expectException(InvalidArgumentException::class);
        (new ChangeSponsor)($top, $bottom, 'Would loop');
    }

    /** @return array<string, array{0: string}> */
    public static function refusals(): array
    {
        return [
            'no reason' => ['no reason'],
            'self' => ['self'],
            'same sponsor' => ['same sponsor'],
            'banned sponsor' => ['banned sponsor'],
            'company account' => ['company account'],
        ];
    }

    #[DataProvider('refusals')]
    public function test_invalid_changes_are_refused_and_nothing_is_logged(string $case)
    {
        $sponsor = $this->member();
        $member = $this->member($sponsor);
        $banned = $this->member();
        $banned->forceFill(['status' => 'banned'])->save();
        $other = $this->member();

        [$who, $to, $reason] = match ($case) {
            'no reason' => [$member, $other, '  '],
            'self' => [$member, $member, 'x'],
            'same sponsor' => [$member, $sponsor, 'x'],
            'banned sponsor' => [$member, $banned, 'x'],
            'company account' => [$this->company, $other, 'x'],
        };

        try {
            (new ChangeSponsor)($who, $to, $reason);
            $this->fail('The change should have been refused.');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, SponsorChange::count());
            $this->assertSame($sponsor->id, $member->fresh()->sponsor_id);
        }
    }
}
