<?php

use App\Http\Controllers\Platform\HealthController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Platform/Demo'))
    ->name('platform.demo');

Route::get('/up', HealthController::class)
    ->name('health');

if (app()->environment('testing')) {
    Route::get('/__test/login-rate-limit', fn (): Response => response()->noContent())
        ->middleware('throttle:login')
        ->name('testing.login-rate-limit');

    Route::post('/__test/csrf', fn (): Response => response()->noContent())
        ->name('testing.csrf');

    Route::get('/__test/errors/{status}', function (int $status): never {
        if ($status === 500) {
            throw new RuntimeException('Panne de fixture contrôlée.');
        }

        abort($status);
    })->whereIn('status', [403, 419, 500])->name('testing.errors');
}
