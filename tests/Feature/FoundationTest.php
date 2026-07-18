<?php

namespace Tests\Feature;

use Illuminate\Foundation\Application;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_ac_1_uses_the_required_php_laravel_and_slim_bootstrap(): void
    {
        $this->assertGreaterThanOrEqual(80300, PHP_VERSION_ID);
        $this->assertStringStartsWith('13.', Application::VERSION);
        $this->assertFileExists(base_path('bootstrap/app.php'));
        $this->assertFileExists(base_path('routes/web.php'));

        $this->get(route('platform.demo'))->assertOk();
    }

    public function test_ac_2_renders_the_named_inertia_demo_page(): void
    {
        $this->get(route('platform.demo'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Platform/Demo'));
    }

    public function test_ac_3_exposes_all_five_module_structures(): void
    {
        $modules = ['Platform', 'Identity', 'Work', 'Accountability', 'Finance'];
        $roots = [
            app_path('Http/Controllers'),
            app_path('Models'),
            app_path('Policies'),
            app_path('Services'),
            resource_path('js/Pages'),
        ];

        foreach ($roots as $root) {
            foreach ($modules as $module) {
                $this->assertDirectoryExists($root.'/'.$module);
            }
        }
    }

    public function test_ac_6_quality_commands_are_installed(): void
    {
        $package = json_decode((string) file_get_contents(base_path('package.json')), true);

        $this->assertFileExists(base_path('vendor/bin/pint'));
        $this->assertFileExists(base_path('vendor/bin/phpstan'));
        $this->assertSame('vite build', $package['scripts']['build'] ?? null);
    }
}
