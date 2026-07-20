<?php

namespace Tests\Feature\Http;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

class PublicAccountCreationSurfaceTest extends TestCase
{
    public function test_ac_1_no_declared_public_route_can_create_an_account(): void
    {
        foreach (['register', 'password.request', 'password.reset'] as $forbiddenName) {
            $this->assertFalse(Route::has($forbiddenName), "La route {$forbiddenName} ne doit pas exister.");
        }

        $publicMutations = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn (IlluminateRoute $route): bool => ! collect($route->gatherMiddleware())
                ->contains(static fn (string $middleware): bool => $middleware === 'auth'
                    || str_starts_with($middleware, 'auth:')))
            ->filter(static fn (IlluminateRoute $route): bool => array_intersect(
                $route->methods(),
                ['POST', 'PUT', 'PATCH', 'DELETE'],
            ) !== []);

        foreach ($publicMutations as $route) {
            $surface = mb_strtolower(implode(' ', [
                $route->uri(),
                (string) $route->getName(),
                $route->getActionName(),
            ]));

            $this->assertDoesNotMatchRegularExpression(
                '/register|inscription|(?:user|account|compte).*(?:create|store|creer)/i',
                $surface,
                "Une route publique semble créer un compte : {$surface}",
            );

            if (! str_contains($route->getActionName(), '@')) {
                continue;
            }

            [$controller, $method] = explode('@', $route->getActionName(), 2);
            $reflection = new ReflectionMethod($controller, $method);
            $fileName = $reflection->getFileName();

            if (! is_string($fileName)) {
                $this->fail("Le contrôleur {$controller} doit provenir d'un fichier inspectable.");
            }

            $lines = file($fileName);

            if ($lines === false) {
                $this->fail("Le contrôleur {$controller} doit être lisible.");
            }

            $source = implode('', array_slice(
                $lines,
                $reflection->getStartLine() - 1,
                $reflection->getEndLine() - $reflection->getStartLine() + 1,
            ));

            $this->assertDoesNotMatchRegularExpression(
                '/new\s+User\b|User::(?:create|forceCreate)\s*\(|->createUser\s*\(/',
                $source,
                "La route publique {$surface} contient une création de compte.",
            );
        }
    }
}
