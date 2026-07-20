<?php

use App\Http\Controllers\Identity\AccountController;
use App\Http\Controllers\Identity\AuthenticationController;
use App\Http\Controllers\Identity\LoginAttemptController;
use App\Http\Controllers\Identity\PasswordController;
use App\Http\Controllers\Identity\PasswordResetController;
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
    Route::get('/connexions', [LoginAttemptController::class, 'index'])
        ->middleware('permission:connexion.consulter')
        ->name('login-attempts.index');
    Route::middleware('permission:compte.gerer|compte.technique.gerer')->group(function (): void {
        Route::get('/comptes', [AccountController::class, 'index'])
            ->name('accounts.index');
        Route::post('/comptes', [AccountController::class, 'store'])
            ->name('accounts.store');
        Route::patch('/comptes/{user}/roles', [AccountController::class, 'syncRoles'])
            ->name('accounts.roles.sync');
        Route::patch('/comptes/{user}/archiver', [AccountController::class, 'archive'])
            ->name('accounts.archive');
        Route::post('/comptes/{user}/reinitialisation/initier', [PasswordResetController::class, 'initiate'])
            ->name('accounts.password-reinitialization.initiate');
        Route::post('/comptes/{user}/reinitialisation/confirmer', [PasswordResetController::class, 'confirm'])
            ->name('accounts.password-reinitialization.confirm');
    });
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
