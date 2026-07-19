<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuditMutationSurfaceTest extends TestCase
{
    public function test_ac_4_no_audit_route_accepts_put_patch_or_delete(): void
    {
        $auditRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route): bool {
                $identity = implode(' ', [
                    $route->uri(),
                    (string) $route->getName(),
                    $route->getActionName(),
                ]);

                return str_contains(strtolower($identity), 'audit');
            });

        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $offendingRoutes = $auditRoutes
                ->filter(fn ($route): bool => in_array($method, $route->methods(), true));

            $this->assertCount(0, $offendingRoutes, "Une route d'audit accepte {$method}.");
        }
    }

    public function test_ac_4_no_command_form_request_or_form_mutates_audit_logs(): void
    {
        $commands = collect(array_keys(Artisan::all()))
            ->filter(fn (string $name): bool => str_contains(strtolower($name), 'audit'));
        $requestFiles = is_dir(app_path('Http/Requests'))
            ? collect(File::allFiles(app_path('Http/Requests')))
            : collect();
        $auditRequests = $requestFiles
            ->filter(fn ($file): bool => str_contains(strtolower($file->getFilename()), 'audit'));
        $pageFiles = collect(File::allFiles(resource_path('js/Pages')));
        $auditForms = $pageFiles->filter(function ($file): bool {
            return str_contains(strtolower($file->getRelativePathname()), 'audit')
                && str_contains(strtolower($file->getContents()), '<form');
        });

        $this->assertCount(0, $commands);
        $this->assertCount(0, $auditRequests);
        $this->assertCount(0, $auditForms);
    }
}
