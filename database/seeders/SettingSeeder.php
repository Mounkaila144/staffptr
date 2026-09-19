<?php

namespace Database\Seeders;

use App\Models\Platform\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class SettingSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $effectiveAt = CarbonImmutable::parse('2026-07-21 00:00:00', 'UTC');
        $settings = [
            'working_days' => [['lun', 'mar', 'mer', 'jeu', 'ven'], 'array'],
            'report_deadline_time' => ['17:45', 'time'],
            'report_reminder_minutes' => [60, 'integer'],
            'intern_limit_per_tutor' => [3, 'integer'],
            'reserve_percentage' => [20, 'percentage'],
            'reserve_target_months' => [3, 'integer'],
            'attachment_allowed_types' => [['pdf', 'jpeg', 'png', 'webp', 'heic'], 'array'],
            'attachment_max_size_bytes' => [8 * 1024 * 1024, 'integer'],
            'login_max_failed_attempts' => [5, 'integer'],
            'login_lockout_minutes' => [15, 'integer'],
            'contract_end_warning_days' => [30, 'integer'],
        ];

        foreach ($settings as $key => [$value, $type]) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'type' => $type, 'effective_at' => $effectiveAt],
            );
        }

        Cache::forget('settings:all');
    }
}
