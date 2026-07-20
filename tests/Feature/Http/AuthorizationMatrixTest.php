<?php

namespace Tests\Feature\Http;

use App\Models\Identity\User;
use App\Services\Identity\RoleAssignmentService;
use Closure;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Inertia;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\Permission\Models\Role;
use Tests\Support\IdentityTestCase;

class AuthorizationMatrixTest extends IdentityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();

        foreach ($this->matrixRoutes() as $routeName => $entry) {
            Route::middleware([
                'web',
                'auth',
                'account.active',
                'password.changed',
                "permission:{$entry['permission']}",
            ])->match([$entry['method'] ?? 'GET'], $entry['path'], fn () => response()->noContent())
                ->name($routeName);
        }
    }

    public function test_ac_1_matrix_retains_the_dated_prd_4_3_review_and_structural_rules(): void
    {
        $source = $this->prdSource();

        $this->assertSame('docs/prd.md#43-matrice-daccès', $source['reference']);
        $this->assertMatchesRegularExpression('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $source['reviewed_on']);
        $this->assertSame('Epic 2', $source['reviewed_for_milestone']);
        $this->assertSame('docs/ops/authorization-quality-gate.md', $source['quality_gate_runbook']);
        $this->assertSame(
            ['super_admin', 'direction', 'finance', 'tuteur', 'employe', 'stagiaire'],
            $this->matrixRoles(),
        );

        $this->assertOnlyRolesAllowed('accounts.index', ['super_admin', 'direction']);
        $this->assertOnlyRolesAllowed('audit.index', ['direction']);
        $this->assertOnlyRolesAllowed('audit.export', ['direction']);
        $this->assertOnlyRolesAllowed('testing.authorization.expense.approve', ['direction']);
        $this->assertOnlyRolesAllowed('testing.authorization.objective.validate', ['direction']);
        $this->assertOnlyRolesAllowed('testing.authorization.financial-report.validate', ['direction']);

        foreach ($this->matrixRoutes() as $entry) {
            $permissions = explode('|', $entry['permission']);

            foreach ($this->matrixRoles() as $roleName) {
                $expectedStatus = Role::findByName($roleName)->hasAnyPermission($permissions) ? 204 : 403;

                $this->assertSame($expectedStatus, $entry['statuses'][$roleName]);
            }
        }
    }

    public function test_ac_2_authorization_matrix_checks_every_role_and_route_by_direct_url(): void
    {
        $service = app(RoleAssignmentService::class);

        foreach ($this->matrixRoles() as $roleName) {
            session()->flush();
            $user = User::factory()->active()->create();
            $service->assignRole($user, $roleName, null, 'Campagne autorisation');

            foreach ($this->matrixRoutes() as $routeName => $entry) {
                $path = $this->resolvedPath($entry['path'], $user);
                $response = $this->actingAs($user)->call($entry['method'] ?? 'GET', $path);

                $this->assertExpectedStatus($response, $entry['statuses'][$roleName], $roleName, $routeName);
            }
        }
    }

    public function test_ac_3_every_protected_route_is_declared_and_every_fixture_has_an_enforced_retirement(): void
    {
        $this->assertEveryProtectedRouteIsDeclared();

        foreach ($this->fixtureRoutes() as $fixtureName => $entry) {
            $replacement = $entry['replacement'];
            $this->assertMatchesRegularExpression('/\A[0-9]+\.[0-9]+\z/', $replacement['story']);
            $this->assertFalse(
                Route::has($replacement['route']),
                "La fixture {$fixtureName} doit être retirée : la route réelle {$replacement['route']} existe désormais (story {$replacement['story']}).",
            );
        }

        foreach ([
            'audit.index',
            'audit.export',
            'login-attempts.index',
            'accounts.index',
            'accounts.store',
            'accounts.roles.sync',
            'accounts.archive',
            'accounts.password-reinitialization.initiate',
            'accounts.password-reinitialization.confirm',
        ] as $deliveredRoute) {
            $this->assertArrayHasKey($deliveredRoute, $this->realRoutes());
        }
    }

    public function test_ac_4_authenticated_refusals_and_unknown_routes_are_never_redirects(): void
    {
        $employee = $this->userWithRole('employe');
        $forbidden = $this->actingAs($employee)
            ->get($this->matrixRoutes()['audit.index']['path']);
        $unknown = $this->actingAs($employee)->get('/__test/authorization/route-inconnue');

        $this->assertCleanForbiddenResponse($forbidden, 'audit.index');
        $unknown->assertNotFound();
        $this->assertFalse($unknown->isRedirection(), 'Une route inconnue doit rester un 404, jamais une redirection.');
    }

    public function test_ac_4_unauthenticated_scope_redirects_every_protected_route_to_login(): void
    {
        $scope = $this->accessScope('unauthenticated');
        Auth::logout();

        foreach ($this->allProtectedDeclarations() as $routeName => $entry) {
            $response = $this->call($entry['method'] ?? 'GET', $this->resolvedPath($entry['path']));

            $response->assertStatus($scope['status']);
            $response->assertRedirect(route($scope['redirect_route']));
            $this->assertTrue($response->isRedirection(), "{$routeName} doit rediriger un visiteur vers la connexion.");
        }
    }

    public function test_ac_4_inactive_and_password_change_scopes_hold_on_every_matrix_route(): void
    {
        $inactive = $this->userWithRole('direction', active: false);
        $inactiveScope = $this->accessScope('inactive_account');

        foreach ($this->allProtectedDeclarations() as $routeName => $entry) {
            $response = $this->actingAs($inactive)
                ->call($entry['method'] ?? 'GET', $this->resolvedPath($entry['path'], $inactive));

            $response->assertStatus($inactiveScope['status']);
            $response->assertRedirect(route($inactiveScope['redirect_route']));
            $this->assertGuest();
        }

        $mustChange = $this->userWithRole('direction');
        $mustChange->forceFill(['must_change_password' => true])->saveQuietly();
        $passwordScope = $this->accessScope('password_change_required');

        foreach ($this->matrixRoutes() as $routeName => $entry) {
            $response = $this->actingAs($mustChange)
                ->call($entry['method'] ?? 'GET', $this->resolvedPath($entry['path'], $mustChange));

            $response->assertStatus($passwordScope['status']);
            $response->assertRedirect(route($passwordScope['redirect_route']));
            $this->assertAuthenticatedAs($mustChange);
        }

        $this->assertSame(
            ['password.change.edit', 'password.change.update', 'logout'],
            $passwordScope['except'],
        );
    }

    public function test_ac_5_every_forbidden_combination_renders_only_the_complete_403_page(): void
    {
        $pageSource = (string) file_get_contents(resource_path('js/Pages/Platform/403.vue'));
        $this->assertStringContainsString("Vous n'avez pas accès à cette page.", $pageSource);

        foreach ($this->matrixRoles() as $roleName) {
            session()->flush();
            Auth::forgetGuards();
            $user = $this->userWithRole($roleName);

            foreach ($this->matrixRoutes() as $routeName => $entry) {
                if ($entry['statuses'][$roleName] !== 403) {
                    continue;
                }

                $response = $this->actingAs($user)
                    ->call($entry['method'] ?? 'GET', $this->resolvedPath($entry['path'], $user));

                $this->assertCleanForbiddenResponse($response, "{$roleName} × {$routeName}");
            }
        }
    }

    public function test_ac_6_pull_request_ci_and_milestone_quality_gate_execute_the_campaign(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/pull-request-quality.yml'));
        $runbook = (string) file_get_contents(base_path('docs/ops/authorization-quality-gate.md'));

        $this->assertStringContainsString('pull_request:', $workflow);
        $this->assertStringContainsString('php artisan test', $workflow);
        $this->assertStringContainsString('AuthorizationMatrixTest', $runbook);
        $this->assertStringContainsString('routes réelles', $runbook);
        $this->assertStringContainsString('fixtures', $runbook);
        $this->assertStringContainsString('PRD § 4.3', $runbook);
    }

    public function test_ac_3_injection_of_an_undeclared_protected_route_is_detected(): void
    {
        Route::middleware(['web', 'auth'])
            ->get('/__test/authorization/injected-undeclared', fn () => response()->noContent())
            ->name('testing.authorization.injected.undeclared');

        $this->assertCampaignFailure(
            fn () => $this->assertEveryProtectedRouteIsDeclared(),
            'Routes protégées non déclarées',
        );
    }

    public function test_ac_2_injection_of_a_falsified_expected_status_is_detected(): void
    {
        $finance = $this->userWithRole('finance');
        $entry = $this->matrixRoutes()['audit.index'];
        $response = $this->actingAs($finance)->get($entry['path']);

        $this->assertCampaignFailure(
            fn () => $this->assertExpectedStatus(
                $response,
                204,
                'finance',
                'audit.index',
            ),
            'Statut inattendu',
        );
    }

    public function test_ac_4_injection_of_a_silent_redirect_is_detected(): void
    {
        Route::middleware(['web', 'auth'])
            ->get('/__test/authorization/injected-redirect', fn () => redirect()->route('home'))
            ->name('testing.authorization.injected.redirect');
        $employee = $this->userWithRole('employe');
        $response = $this->actingAs($employee)->get('/__test/authorization/injected-redirect');

        $this->assertCampaignFailure(
            fn () => $this->assertCleanForbiddenResponse($response, 'injection redirection'),
            'Refus transformé en redirection',
        );
    }

    public function test_ac_5_injection_of_forbidden_resource_content_is_detected(): void
    {
        Route::middleware(['web', 'auth'])
            ->get('/__test/authorization/injected-leak', function () {
                return Inertia::render('Platform/403', [
                    'forbidden_resource' => ['nom' => 'Donnée interdite'],
                ])->toResponse(request())->setStatusCode(403);
            })->name('testing.authorization.injected.leak');
        $employee = $this->userWithRole('employe');
        $response = $this->actingAs($employee)->get('/__test/authorization/injected-leak');

        $this->assertCampaignFailure(
            fn () => $this->assertCleanForbiddenResponse($response, 'injection fuite'),
            'Contenu partiel détecté',
        );
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

    private function assertEveryProtectedRouteIsDeclared(): void
    {
        $protectedRouteNames = collect(Route::getRoutes()->getRoutes())
            ->filter(function (IlluminateRoute $route): bool {
                return collect($route->gatherMiddleware())
                    ->contains(static fn (string $middleware): bool => $middleware === 'auth'
                        || str_starts_with($middleware, 'auth:'));
            })
            ->map(static fn (IlluminateRoute $route): ?string => $route->getName())
            ->filter()
            ->unique()
            ->values()
            ->all();
        $declaredRouteNames = array_keys($this->allProtectedDeclarations());
        $missing = array_values(array_diff($protectedRouteNames, $declaredRouteNames));
        $stale = array_values(array_diff($declaredRouteNames, $protectedRouteNames));

        $this->assertSame([], $missing, 'Routes protégées non déclarées : '.implode(', ', $missing));
        $this->assertSame([], $stale, 'Déclarations sans route protégée : '.implode(', ', $stale));
    }

    private function assertExpectedStatus(
        TestResponse $response,
        int $expectedStatus,
        string $roleName,
        string $routeName,
    ): void {
        $this->assertSame(
            $expectedStatus,
            $response->getStatusCode(),
            "Statut inattendu pour {$roleName} × {$routeName}.",
        );
    }

    private function assertCleanForbiddenResponse(TestResponse $response, string $case): void
    {
        $this->assertFalse($response->isRedirection(), "Refus transformé en redirection pour {$case}.");
        $this->assertSame(403, $response->getStatusCode(), "Le refus {$case} doit répondre 403.");
        $page = $response->inertiaPage();
        $props = $page['props'] ?? null;

        $this->assertSame('Platform/403', $page['component'] ?? null, "Le refus {$case} doit rendre la page 403 complète.");
        $this->assertIsArray($props, "Les props Inertia du refus {$case} doivent être inspectables.");
        $this->assertEqualsCanonicalizing(
            ['auth', 'errors'],
            array_keys($props),
            "Contenu partiel détecté dans le refus {$case}.",
        );
    }

    private function assertCampaignFailure(Closure $campaign, string $expectedMessage): void
    {
        try {
            $campaign();
            $this->fail("L'injection devait faire échouer la campagne.");
        } catch (AssertionFailedError $exception) {
            $this->assertStringContainsString($expectedMessage, $exception->getMessage());
        }
    }

    /** @param list<string> $allowedRoles */
    private function assertOnlyRolesAllowed(string $routeName, array $allowedRoles): void
    {
        $entry = $this->matrixRoutes()[$routeName];

        foreach ($this->matrixRoles() as $roleName) {
            $this->assertSame(
                in_array($roleName, $allowedRoles, true) ? 204 : 403,
                $entry['statuses'][$roleName],
                "La règle PRD de {$routeName} diverge pour {$roleName}.",
            );
        }
    }

    private function resolvedPath(string $path, ?User $target = null): string
    {
        return preg_replace('/\{[^}]+\}/', (string) ($target?->getKey() ?? 1), $path) ?? $path;
    }

    private function userWithRole(string $role, bool $active = true): User
    {
        $user = $active ? User::factory()->active()->create() : User::factory()->suspended()->create();
        app(RoleAssignmentService::class)->assignRole($user, $role, null, 'Campagne autorisation 2.9');

        return $user;
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
     *     statuses: array<string, int>,
     *     replacement?: array{route: string, story: string}
     * }>
     */
    private function matrixRoutes(): array
    {
        return [...$this->realRoutes(), ...$this->fixtureRoutes()];
    }

    /**
     * @return array<string, array{path: string, method?: string, permission: string, statuses: array<string, int>}>
     */
    private function realRoutes(): array
    {
        $routes = config('authorization-matrix.routes');
        $this->assertIsArray($routes);

        return $routes;
    }

    /**
     * @return array<string, array{
     *     path: string,
     *     method?: string,
     *     permission: string,
     *     statuses: array<string, int>,
     *     replacement: array{route: string, story: string}
     * }>
     */
    private function fixtureRoutes(): array
    {
        $routes = config('authorization-matrix.fixtures');
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

    /** @return array<string, array{method?: string, path: string}> */
    private function allProtectedDeclarations(): array
    {
        return [...$this->matrixRoutes(), ...$this->authenticationRoutes()];
    }

    /** @return array{status: int, redirect_route: string, except?: list<string>} */
    private function accessScope(string $scope): array
    {
        $configuration = config("authorization-matrix.access_scopes.{$scope}");
        $this->assertIsArray($configuration);

        return $configuration;
    }

    /**
     * @return array{reference: string, reviewed_on: string, reviewed_for_milestone: string, quality_gate_runbook: string}
     */
    private function prdSource(): array
    {
        $source = config('authorization-matrix.prd_source');
        $this->assertIsArray($source);

        return $source;
    }
}
