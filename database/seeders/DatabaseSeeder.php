<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database: the data every installation needs.
     */
    public function run(): void
    {
        $this->call([PackageSeeder::class, CompanyAccountSeeder::class]);
    }
}
