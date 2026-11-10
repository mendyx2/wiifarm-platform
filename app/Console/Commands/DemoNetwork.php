<?php

namespace App\Console\Commands;

use App\Models\Package;
use App\Models\User;
use App\Services\Network\Registration;
use Illuminate\Console\Command;

/**
 * Builds a small sample network for trying the screens: members joining with
 * different packages under different sponsors. Only on a developer's machine
 * or a demo site, never on the live site. Every member's password is
 * "password123".
 *
 *     php artisan wiifarm:demo-network
 */
class DemoNetwork extends Command
{
    protected $signature = 'wiifarm:demo-network';

    protected $description = 'Build a sample network (not for the live site)';

    /** username, first name, last name, sponsor username, package, chosen leg */
    private const MEMBERS = [
        ['adaeze_n', 'Adaeze', 'Nwosu', 'wiifarm', 'Mega', null],
        ['bayo_ade', 'Bayo', 'Adeyemi', 'wiifarm', 'Executive', null],
        ['chika_o', 'Chika', 'Obi', 'adaeze_n', 'Builder', 'L'],
        ['dapo_k', 'Dapo', 'Kolawole', 'adaeze_n', 'Booster', 'R'],
        ['emeka_u', 'Emeka', 'Uche', 'adaeze_n', 'Starter', 'L'],
        ['funmi_a', 'Funmi', 'Ajayi', 'bayo_ade', 'Advance', null],
        ['gbenga_t', 'Gbenga', 'Taiwo', 'bayo_ade', 'Pre Starter', null],
        ['halima_b', 'Halima', 'Bello', 'chika_o', 'Starter', null],
        ['ikenna_m', 'Ikenna', 'Madu', 'dapo_k', 'Builder', null],
        ['jumoke_s', 'Jumoke', 'Salami', 'dapo_k', 'Mega', null],
    ];

    public function handle(Registration $registration): int
    {
        if (app()->environment('production') && ! config('app.demo')) {
            $this->error('Not on the live site.');

            return self::FAILURE;
        }

        foreach (self::MEMBERS as [$username, $first, $last, $sponsor, $package, $leg]) {
            if (User::where('username', $username)->exists()) {
                continue;
            }
            $member = new User([
                'username' => $username, 'first_name' => $first, 'last_name' => $last,
                'email' => "{$username}@example.com", 'dial_code' => '234', 'mobile' => '80'.random_int(10_000_000, 99_999_999),
                'country' => 'NG', 'password' => 'password123',
            ]);
            $member->sponsor_id = User::where('username', $sponsor)->value('id');
            $member->requested_leg = $leg;
            $member->email_verified_at = now();
            $member->save();

            $registration->activate($member, Package::where('name', $package)->firstOrFail(), "demo:{$username}");
            $this->line("@{$username} joined with {$package} under @{$sponsor}");
        }

        $this->info('Demo network ready. Passwords: password123');

        return self::SUCCESS;
    }
}
