<?php

use App\Http\Controllers\Identity\AuthenticationController;
use App\Http\Controllers\Identity\PasswordController;
use App\Http\Controllers\Platform\HealthController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/up', HealthController::class)
    ->name('health');

Route::middleware('guest')->group(function (): void {
    Route::get('/connexion', [AuthenticationController::class, 'create'])
        ->name('login');
    Route::post('/connexion', [AuthenticationController::class, 'store'])
        ->name('login.store');
});

Route::middleware(['auth', 'account.active', 'password.changed'])->group(function (): void {
    Route::get('/', fn () => Inertia::render('Identity/Home'))
        ->name('home');
    Route::get('/mot-de-passe/modifier', [PasswordController::class, 'edit'])
        ->name('password.change.edit');
    Route::patch('/mot-de-passe', [PasswordController::class, 'update'])
        ->name('password.change.update');
    Route::post('/deconnexion', [AuthenticationController::class, 'destroy'])
        ->name('logout');
});

if (app()->environment('testing')) {
    Route::get('/__test/interface-demo', fn () => Inertia::render('Platform/Demo', [
        'auth' => [
            'permissions' => [
                'role:direction',
                'role:finance',
                'tableau_bord.consulter',
                'depense.approuver',
                'stagiaire.consulter',
                'finance.ecriture.consulter',
                'depense.consulter',
                'client.consulter',
            ],
        ],
    ]))->name('platform.demo');

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
