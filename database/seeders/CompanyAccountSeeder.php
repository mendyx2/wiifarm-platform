<?php

namespace Database\Seeders;

use App\Models\TreePosition;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the company account, member #1, if it does not exist yet. It has no
 * sponsor and sits at the top of the network. Its password is random: set a
 * real one with the "forgot password" link before using it.
 */
class CompanyAccountSeeder extends Seeder
{
    public function run(): void
    {
        if ($existing = User::company()) {
            $this->placeAtTheTop($existing);

            return;
        }

        $company = new User([
            'username' => config('wiifarm.company_username'),
            'first_name' => 'Wiifarm',
            'last_name' => 'Cooperative',
            'email' => env('WIIFARM_COMPANY_EMAIL', 'company@wiifarm.example'),
            'dial_code' => config('wiifarm.default_dial_code'),
            'mobile' => '0000000000',
            'country' => config('wiifarm.default_country'),
            'password' => Str::random(40),
        ]);
        $company->activated_at = now();
        $company->email_verified_at = now();
        $company->save();

        $this->placeAtTheTop($company);
    }

    /** The company account is the top of the binary tree (section 3). */
    private function placeAtTheTop(User $company): void
    {
        if (! TreePosition::whereNull('parent_id')->exists()) {
            TreePosition::create(['user_id' => $company->id, 'parent_id' => null, 'leg' => null, 'depth' => 0]);
        }
    }
}
