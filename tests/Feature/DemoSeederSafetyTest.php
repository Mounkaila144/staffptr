<?php

namespace Tests\Feature;

use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Models\Platform\AuditLog;
use Database\Seeders\DemoSeeder;
use LogicException;
use Tests\Support\IdentityTestCase;

class DemoSeederSafetyTest extends IdentityTestCase
{
    public function test_ac_10_demo_seeder_refuses_production_before_writing_any_data(): void
    {
        $before = [
            Person::query()->count(),
            User::query()->count(),
            AuditLog::query()->count(),
        ];
        $previousEnvironment = app()->environment();
        app()->instance('env', 'production');

        try {
            $this->expectException(LogicException::class);
            $this->seed(DemoSeeder::class);
        } finally {
            app()->instance('env', $previousEnvironment);
            $this->assertSame($before, [
                Person::query()->count(),
                User::query()->count(),
                AuditLog::query()->count(),
            ]);
        }
    }

    public function test_ac_10_demo_seeder_uses_factories_outside_production(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(3, Person::query()->count());
        $this->assertSame(3, User::query()->count());
    }

    public function test_task_6_database_seeder_does_not_mix_demo_data_with_reference_data(): void
    {
        $source = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringNotContainsString('DemoSeeder', $source);
    }
}
