<?php

namespace Database\Seeders;

use App\Enums\CompanyPriorityState;
use App\Enums\WorkPriority;
use App\Models\Identity\User;
use App\Models\Work\CompanyPriority;
use Illuminate\Database\Seeder;

class CompanyPrioritySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = User::query()->where('state', 'actif')->orderBy('id')->first();
        if (! $owner instanceof User) {
            return;
        }
        CompanyPriority::query()->updateOrCreate(['month' => '2026-08-01', 'title' => 'Fiabiliser le travail du mois'], ['description' => 'Rendre les engagements et les preuves visibles.', 'owner_id' => $owner->getKey(), 'indicator' => 'Part des objectifs suivis', 'target' => '100 %', 'due_date' => '2026-08-31', 'priority' => WorkPriority::Haute, 'state' => CompanyPriorityState::Validee]);
    }
}
