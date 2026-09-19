<?php

namespace Database\Seeders;

use App\Models\Platform\Holiday;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $year = CarbonImmutable::now('Africa/Niamey')->year;

        foreach ($this->holidaysFor($year) as $date => $label) {
            Holiday::query()->updateOrCreate(
                ['date' => $date],
                ['label' => $label, 'is_active' => true],
            );
        }
    }

    /** @return array<string, string> */
    private function holidaysFor(int $year): array
    {
        $fixed = [
            "{$year}-01-01" => "Jour de l'An",
            "{$year}-03-26" => 'Journée nationale de la Refondation',
            "{$year}-04-24" => 'Journée nationale de la Concorde',
            "{$year}-05-01" => 'Fête du Travail',
            "{$year}-07-26" => 'Journée nationale de la Souveraineté et du Patriotisme',
            "{$year}-08-03" => "Fête de l'Indépendance",
            "{$year}-12-18" => 'Fête de la République',
            "{$year}-12-25" => 'Noël',
        ];

        if ($year !== 2026) {
            return $fixed;
        }

        return $fixed + [
            '2026-03-16' => 'Lendemain de la nuit de Lailatoul Qadr',
            '2026-03-19' => 'Aïd el-Fitr',
            '2026-03-20' => "Lendemain de l'Aïd el-Fitr",
            '2026-04-06' => 'Lundi de Pâques',
            '2026-05-27' => 'Tabaski',
            '2026-05-28' => 'Lendemain de la Tabaski',
            '2026-06-17' => 'Nouvel An musulman',
            '2026-08-26' => 'Mouloud',
        ];
    }
}
