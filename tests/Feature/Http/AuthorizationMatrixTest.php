<?php

namespace Tests\Feature\Http;

use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\Support\IdentityTestCase;

class AuthorizationMatrixTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        foreach ($this->matrixRoutes() as $routeName => $entry) {
            Route::middleware(['web', 'auth', "permission:{$entry['permission']}"])
                ->match([$entry['method'] ?? 'GET'], $entry['path'], fn () => response()->noContent())
                ->name($routeName);
        }
    }

    public function test_ac_5_authorization_matrix_returns_exact_status_for_every_role_and_route(): void
    {
        $service = app(RoleAssignmentService::class);

        foreach ($this->matrixRoles() as $roleName) {
            session()->flush();
            $user = User::factory()->active()->create();
            $service->assignRole($user, $roleName, null, 'Campagne autorisation');

            foreach ($this->matrixRoutes() as $routeName => $entry) {
                $expectedStatus = $entry['statuses'][$roleName];
                $path = preg_replace('/\{[^}]+\}/', (string) $user->getKey(), $entry['path']);

                $this->actingAs($user)
                    ->call($entry['method'] ?? 'GET', $path ?? $entry['path'])
                    ->assertStatus($expectedStatus);
            }
        }
    }

    public function test_ac_5_every_protected_route_is_declared_in_authorization_matrix(): void
    {
        $protectedRouteNames = collect(Route::getRoutes()->getRoutes())
            ->filter(function (IlluminateRoute $route): bool {
                return collect($route->gatherMiddleware())
                    ->contains(static fn (string $middleware): bool => $middleware === 'auth'
                        || str_starts_with($middleware, 'auth:'));
            })
            ->map(static fn (IlluminateRoute $route): ?string => $route->getName())
            ->filter()
            ->values()
            ->all();

        $declaredRouteNames = [
            ...array_keys($this->matrixRoutes()),
            ...array_keys($this->authenticationRoutes()),
        ];

        $this->assertEqualsCanonicalizing($declaredRouteNames, $protectedRouteNames);
    }

    public function test_ac_5_matrix_is_consistent_with_catalog_permissions(): void
    {
        foreach ($this->matrixRoutes() as $entry) {
            $permissions = explode('|', $entry['permission']);

            foreach ($this->matrixRoles() as $roleName) {
                $expectedStatus = Role::findByName($roleName)->hasAnyPermission($permissions) ? 204 : 403;

                $this->assertSame($expectedStatus, $entry['statuses'][$roleName]);
            }
        }
    }

    public function test_story_2_4_authentication_route_declarations_match_the_router(): void
    {
        foreach ($this->authenticationRoutes() as $routeName => $declaration) {
            $route = Route::getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "La route {$routeName} doit exister.");
            $this->assertContains($declaration['method'], $route->methods());
            $expectedUri = $declaration['path'] === '/' ? '/' : ltrim($declaration['path'], '/');
            $this->assertSame($expectedUri, $route->uri());
        }
    }

    public function test_ac_5_forbidden_and_unknown_responses_are_never_silent_redirects(): void
    {
        $employee = User::factory()->active()->create();
        app(RoleAssignmentService::class)->assignRole($employee, 'employe', null, 'Campagne autorisation');

        $this->actingAs($employee)
            ->get($this->matrixRoutes()['testing.authorization.audit.view']['path'])
            ->assertStatus(403);
        $this->actingAs($employee)
            ->get('/__test/authorization/route-inconnue')
            ->assertStatus(404);
    }

    /** @return list<string> */
    private function matrixRoles(): array
    {
        $roles = config('authorization-matrix.roles');

        $this->assertIsArray($roles);

        return array_values(array_map(static fn (mixed $role): string => (string) $role, $roles));
    }

    /**
     * @return array<string, array{
     *     path: string,
     *     method?: string,
     *     permission: string,
     *     statuses: array<string, int>
     * }>
     */
    private function matrixRoutes(): array
    {
        $routes = config('authorization-matrix.routes');

        $this->assertIsArray($routes);

        return $routes;
    }

    /** @return array<string, array{method: string, path: string}> */
    private function authenticationRoutes(): array
    {
        $routes = config('authorization-matrix.authentication_routes');

        $this->assertIsArray($routes);

        return $routes;
    }
}
