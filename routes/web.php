<?php

use App\Http\Controllers\Identity\AccountController;
use App\Http\Controllers\Identity\AuthenticationController;
use App\Http\Controllers\Identity\LoginAttemptController;
use App\Http\Controllers\Identity\OrganizationController;
use App\Http\Controllers\Identity\PasswordController;
use App\Http\Controllers\Identity\PasswordResetController;
use App\Http\Controllers\Identity\PersonDocumentController;
use App\Http\Controllers\Identity\PersonProfileController;
use App\Http\Controllers\Platform\AttachmentController;
use App\Http\Controllers\Platform\AttachmentThumbnailController;
use App\Http\Controllers\Platform\AuditLogController;
use App\Http\Controllers\Platform\HealthController;
use App\Http\Controllers\Platform\NotificationController;
use App\Http\Controllers\Platform\SettingController;
use App\Models\Identity\User;
use App\Services\Platform\NotificationReadService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/up', HealthController::class)
    ->name('health');

Route::get('/pieces-jointes/{attachment}/vignette', AttachmentThumbnailController::class)
    ->middleware('signed')
    ->name('attachments.thumbnail');

Route::middleware('guest')->group(function (): void {
    Route::get('/connexion', [AuthenticationController::class, 'create'])
        ->name('login');
    Route::post('/connexion', [AuthenticationController::class, 'store'])
        ->name('login.store');
});

Route::middleware(['auth', 'account.active', 'password.changed'])->group(function (): void {
    Route::get('/', function (Request $request, NotificationReadService $notificationReadService) {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $notificationReadService->markForLink($user, route('home', absolute: false));

        return Inertia::render('Identity/Home');
    })
        ->name('home');
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::patch('/notifications/{notification}/lue', [NotificationController::class, 'read'])
        ->name('notifications.read');
    Route::patch('/notifications/tout-lu', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');
    Route::get('/mot-de-passe/modifier', [PasswordController::class, 'edit'])
        ->name('password.change.edit');
    Route::patch('/mot-de-passe', [PasswordController::class, 'update'])
        ->name('password.change.update');
    Route::post('/deconnexion', [AuthenticationController::class, 'destroy'])
        ->name('logout');
    Route::get('/connexions', [LoginAttemptController::class, 'index'])
        ->middleware('permission:connexion.consulter')
        ->name('login-attempts.index');
    Route::middleware('permission:audit.consulter')->group(function (): void {
        Route::get('/journal-audit', [AuditLogController::class, 'index'])
            ->name('audit.index');
        Route::get('/journal-audit/export', [AuditLogController::class, 'export'])
            ->name('audit.export');
    });
    Route::middleware('permission:parametre.gerer')->group(function (): void {
        Route::get('/parametres', [SettingController::class, 'index'])
            ->name('settings.index');
        Route::patch('/parametres', [SettingController::class, 'update'])
            ->name('settings.update');
        Route::post('/parametres/apercu', [SettingController::class, 'preview'])
            ->name('settings.preview');
    });
    Route::get('/personnes/{person}', [PersonProfileController::class, 'show'])
        ->middleware('permission:fiche.consulter')
        ->name('people.show');
    Route::get('/personnes/{person}/historique', [PersonProfileController::class, 'history'])
        ->middleware('permission:fiche.consulter')
        ->name('people.history');
    Route::get('/personnes/{person}/documents', [PersonDocumentController::class, 'index'])
        ->middleware('permission:fiche.consulter')
        ->name('people.documents.index');
    Route::post('/personnes/{person}/documents', [PersonDocumentController::class, 'store'])
        ->middleware('permission:fiche.gerer')
        ->name('people.documents.store');
    Route::get('/personnes/{person}/documents/{document}', [PersonDocumentController::class, 'show'])
        ->middleware('permission:fiche.consulter')
        ->name('people.documents.show');
    Route::patch('/personnes/{person}/documents/{document}/archiver', [PersonDocumentController::class, 'archive'])
        ->middleware('permission:fiche.gerer')
        ->name('people.documents.archive');
    Route::post('/internal/v1/attachments', [AttachmentController::class, 'store'])
        ->middleware('permission:piece_jointe.creer')
        ->name('attachments.store');
    Route::get('/pieces-jointes/{attachment}', [AttachmentController::class, 'show'])
        ->middleware('permission:piece_jointe.consulter')
        ->name('attachments.show');
    Route::patch('/personnes/{person}', [PersonProfileController::class, 'update'])
        ->middleware('permission:fiche.gerer')
        ->name('people.update');
    Route::middleware('permission:organisation.gerer')->group(function (): void {
        Route::get('/organisation', [OrganizationController::class, 'index'])
            ->name('organisation.index');
        Route::patch('/organisation/entreprise', [OrganizationController::class, 'updateCompany'])
            ->name('organisation.company.update');
        Route::post('/organisation/services', [OrganizationController::class, 'storeDepartment'])
            ->name('organisation.departments.store');
        Route::patch('/organisation/services/{department}', [OrganizationController::class, 'updateDepartment'])
            ->name('organisation.departments.update');
        Route::patch('/organisation/services/{department}/desactiver', [OrganizationController::class, 'deactivateDepartment'])
            ->name('organisation.departments.deactivate');
        Route::patch('/organisation/services/{department}/reactiver', [OrganizationController::class, 'reactivateDepartment'])
            ->name('organisation.departments.reactivate');
        Route::post('/organisation/fonctions', [OrganizationController::class, 'storeJobFunction'])
            ->name('organisation.functions.store');
        Route::patch('/organisation/fonctions/{jobFunction}', [OrganizationController::class, 'updateJobFunction'])
            ->name('organisation.functions.update');
        Route::patch('/organisation/fonctions/{jobFunction}/desactiver', [OrganizationController::class, 'deactivateJobFunction'])
            ->name('organisation.functions.deactivate');
        Route::patch('/organisation/fonctions/{jobFunction}/reactiver', [OrganizationController::class, 'reactivateJobFunction'])
            ->name('organisation.functions.reactivate');
    });
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
