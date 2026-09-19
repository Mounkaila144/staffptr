<?php

namespace Database\Seeders;

use App\Models\Identity\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::query()->updateOrCreate(
            ['id' => 1],
            ['name' => 'PTR Niger'],
        );
    }
}
