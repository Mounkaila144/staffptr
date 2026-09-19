<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CompanySeeder::class,
            SettingSeeder::class,
            HolidaySeeder::class,
            ExpenseCategorySeeder::class,
            FinancialAccountSeeder::class,
            FixedChargeSeeder::class,
            CompanyPrioritySeeder::class,
            ProjectSeeder::class,
            ObjectiveSeeder::class,
            TaskSeeder::class,
            DeliverableSeeder::class,
            DailyReportSeeder::class,
            TaskRequestSeeder::class,
            BlockerSeeder::class,
            WeeklyReviewSeeder::class,
            InternshipSeeder::class,
        ]);
    }
}
