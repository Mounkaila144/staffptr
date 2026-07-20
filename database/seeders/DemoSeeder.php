<?php

namespace Database\Seeders;

use App\Models\Identity\Person;
use App\Models\Identity\User;
use Illuminate\Database\Seeder;
use LogicException;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('Les données de démonstration sont interdites en production.');
        }

        Person::factory()
            ->count(3)
            ->create()
            ->each(static function (Person $person): void {
                User::factory()->for($person)->active()->create();
            });
    }
}
