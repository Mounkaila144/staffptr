<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('up'),
        );

        $exceptions->respond(function (Response $response, Throwable $_exception, Request $request): Response {
            $status = $response->getStatusCode();

            if ($request->is('up') || $request->expectsJson() || app()->environment('local')) {
                return $response;
            }

            if (! in_array($status, [403, 404, 419, 500], true)) {
                return $response;
            }

            $props = $status === 500
                ? ['reference' => substr((string) $request->attributes->get('request_id'), 0, 8)]
                : [];

            $errorResponse = Inertia::render("Platform/{$status}", $props)
                ->toResponse($request)
                ->setStatusCode($status);

            return app(SecurityHeaders::class)->apply($request, $errorResponse);
        });
    })->create();
