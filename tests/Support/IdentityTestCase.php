<?php

namespace Tests\Support;

use App\Models\Platform\Setting;
use App\Services\Platform\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

abstract class IdentityTestCase extends TestCase
{
    use DatabaseTransactions;
    use UsesSeparatedDatabaseConnections;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->migrationSchema()->hasTable('users') || ! $this->migrationSchema()->hasTable('roles')) {
            $exitCode = Artisan::call('migrate', [
                '--database' => $this->migrationConnectionName(),
                '--force' => true,
            ]);

            $this->assertSame(0, $exitCode, Artisan::output());
        }

        $this->seed(SettingSeeder::class);
    }

    protected function seedRbac(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function setSetting(string $key, mixed $value): void
    {
        $setting = Setting::query()->where('key', $key)->firstOrFail();
        $setting->forceFill(['value' => $value])->saveQuietly();
        Cache::forget(SettingsService::CACHE_KEY);
    }
}
