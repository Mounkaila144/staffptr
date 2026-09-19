<?php

namespace Database\Seeders;

use App\Models\Finance\ExpenseCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ($this->categories() as $name => $attributes) {
            ExpenseCategory::query()->updateOrCreate(
                ['name' => $name],
                [
                    'is_essential' => $attributes['is_essential'],
                    'is_active' => true,
                ],
            );
        }
    }

    /** @return array<string, array{is_essential: bool}> */
    private function categories(): array
    {
        return [
            'Salaires' => ['is_essential' => true],
            'Charges sociales et fiscales' => ['is_essential' => true],
            'Loyer et charges fixes' => ['is_essential' => true],
            'Gratification de stagiaire' => ['is_essential' => false],
            'Fonctionnement courant' => ['is_essential' => false],
            'Déplacements et missions' => ['is_essential' => false],
        ];
    }
}
