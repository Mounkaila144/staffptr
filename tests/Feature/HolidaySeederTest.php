<?php

namespace Tests\Feature;

use App\Models\Platform\Holiday;
use Carbon\CarbonImmutable;
use Database\Seeders\HolidaySeeder;
use Tests\Support\IdentityTestCase;

class HolidaySeederTest extends IdentityTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_ac_2_current_nigerien_holidays_are_seeded_idempotently_for_2026(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-21 10:00:00', 'Africa/Niamey'));

        $this->seed(HolidaySeeder::class);
        $this->seed(HolidaySeeder::class);

        $this->assertSame(16, Holiday::query()->count());
        $this->assertDatabaseHas('holidays', [
            'date' => '2026-03-16',
            'label' => 'Lendemain de la nuit de Lailatoul Qadr',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('holidays', ['date' => '2026-03-19', 'label' => 'Aïd el-Fitr']);
        $this->assertDatabaseHas('holidays', ['date' => '2026-03-20', 'label' => "Lendemain de l'Aïd el-Fitr"]);
        $this->assertDatabaseHas('holidays', ['date' => '2026-03-26', 'label' => 'Journée nationale de la Refondation']);
        $this->assertDatabaseHas('holidays', ['date' => '2026-05-27', 'label' => 'Tabaski']);
        $this->assertDatabaseHas('holidays', ['date' => '2026-05-28', 'label' => 'Lendemain de la Tabaski']);
        $this->assertDatabaseHas('holidays', ['date' => '2026-06-17', 'label' => 'Nouvel An musulman']);
        $this->assertDatabaseHas('holidays', ['date' => '2026-07-26', 'label' => 'Journée nationale de la Souveraineté et du Patriotisme']);
    }
}
