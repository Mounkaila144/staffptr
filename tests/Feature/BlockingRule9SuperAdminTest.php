<?php

namespace Tests\Feature;

use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Support\IdentityTestCase;

class BlockingRule9SuperAdminTest extends IdentityTestCase
{
    /** @var array<string, string> */
    private array $powers = [
        'testing.blocking-rule-9.expense-approve' => 'depense.approuver',
        'testing.blocking-rule-9.objective-validate' => 'objectif.valider',
        'testing.blocking-rule-9.financial-report-validate' => 'rapport_financier.valider',
        'testing.blocking-rule-9.audit-view' => 'audit.consulter',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
        Gate::policy(ProtectedPower::class, ProtectedPowerPolicy::class);

        foreach ($this->powers as $routeName => $permission) {
            $slug = str_replace('.', '-', $routeName);

            Route::middleware(['web', 'auth', "permission:{$permission}"])
                ->get("/__test/blocking-rule-9/{$slug}", function () use ($permission) {
                    Gate::authorize('exercise', new ProtectedPower($permission));

                    return response()->noContent();
                })
                ->name($routeName);
        }
    }

    public function test_blocking_rule_9_super_admin_receives_403_for_all_four_business_powers(): void
    {
        $superAdmin = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($superAdmin, 'super_admin', null, 'Test RBAC');

        foreach (array_keys($this->powers) as $routeName) {
            $this->actingAs($superAdmin)
                ->get($this->routePath($routeName))
                ->assertStatus(403);
        }

        foreach ($this->powers as $permission) {
            $this->assertTrue(
                Gate::forUser($superAdmin)->denies('exercise', new ProtectedPower($permission)),
                "La policy devait refuser {$permission} au super administrateur.",
            );
        }
    }

    public function test_blocking_rule_9_direction_is_authorized_by_middleware_and_fixture_policy(): void
    {
        $direction = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($direction, 'direction', null, 'Test RBAC');

        foreach (array_keys($this->powers) as $routeName) {
            $this->actingAs($direction)->get($this->routePath($routeName))->assertNoContent();
        }
    }

    public function test_blocking_rule_9_no_global_super_admin_gate_shortcut_exists(): void
    {
        $roots = [app_path(), base_path('bootstrap')];

        foreach ($roots as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());

                $this->assertIsString($contents);
                $this->assertDoesNotMatchRegularExpression(
                    '/Gate\s*::\s*before\s*\(/',
                    $contents,
                    "Un Gate::before() global est interdit : {$file->getPathname()}",
                );
            }
        }
    }

    private function routePath(string $routeName): string
    {
        return '/__test/blocking-rule-9/'.str_replace('.', '-', $routeName);
    }
}

final class ProtectedPower
{
    public function __construct(public readonly string $permission) {}
}

final class ProtectedPowerPolicy
{
    public function exercise(User $user, ProtectedPower $power): bool
    {
        return $user->can($power->permission);
    }
}
