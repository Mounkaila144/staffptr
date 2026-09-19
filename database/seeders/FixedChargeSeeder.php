<?php

namespace Database\Seeders;

use App\Models\Finance\FixedCharge;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FixedChargeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ($this->labels() as $label) {
            $existing = FixedCharge::query()->firstOrNew(['label' => $label]);

            FixedCharge::query()->updateOrCreate(
                ['label' => $label],
                [
                    'monthly_amount' => $existing->exists
                        ? (int) $existing->getRawOriginal('monthly_amount')
                        : 0,
                    'is_active' => $existing->exists
                        ? (bool) $existing->getRawOriginal('is_active')
                        : true,
                ],
            );
        }
    }

    /** @return list<string> */
    private function labels(): array
    {
        return ['Loyer', 'Électricité', 'Internet', 'Salaires'];
    }
}
