<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Models\Identity\User;
use App\Models\Work\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $manager = User::query()->where('state', 'actif')->orderBy('id')->first();
        if (! $manager instanceof User) {
            return;
        }
        Project::query()->updateOrCreate(['name' => 'Socle opérationnel PTR'], ['client_name' => null, 'manager_id' => $manager->getKey(), 'start_date' => '2026-08-01', 'end_date' => '2026-08-31', 'status' => ProjectStatus::Actif]);
    }
}
