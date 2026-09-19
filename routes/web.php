<?php

use App\Http\Controllers\Accountability\BlockerController;
use App\Http\Controllers\Accountability\DailyReportAnalyticsController;
use App\Http\Controllers\Accountability\DailyReportController;
use App\Http\Controllers\Accountability\DailyReportReviewController;
use App\Http\Controllers\Accountability\ImprovementPlanController;
use App\Http\Controllers\Accountability\InternshipController;
use App\Http\Controllers\Accountability\InternshipIntakeController;
use App\Http\Controllers\Accountability\TaskRequestController;
use App\Http\Controllers\Accountability\TutorCapacityController;
use App\Http\Controllers\Accountability\TutorSupportSlotController;
use App\Http\Controllers\Accountability\WeeklyReviewController;
use App\Http\Controllers\Finance\ClientController;
use App\Http\Controllers\Finance\ContractController;
use App\Http\Controllers\Finance\CorrectionPlanController;
use App\Http\Controllers\Finance\ExpenseApprovalController;
use App\Http\Controllers\Finance\ExpenseCategoryController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\ExpensePaymentController;
use App\Http\Controllers\Finance\FinancialAccountController;
use App\Http\Controllers\Finance\FinancialDashboardController;
use App\Http\Controllers\Finance\FixedChargeController;
use App\Http\Controllers\Finance\InvoiceController;
use App\Http\Controllers\Finance\MonthlyBudgetController;
use App\Http\Controllers\Finance\MonthlyReportController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\ReconciliationController;
use App\Http\Controllers\Finance\ReserveController;
use App\Http\Controllers\Finance\ShareEntitlementController;
use App\Http\Controllers\Identity\AbsenceController;
use App\Http\Controllers\Identity\AccountController;
use App\Http\Controllers\Identity\AuthenticationController;
use App\Http\Controllers\Identity\HomeController;
use App\Http\Controllers\Identity\LoginAttemptController;
use App\Http\Controllers\Identity\OrganizationController;
use App\Http\Controllers\Identity\PasswordController;
use App\Http\Controllers\Identity\PasswordResetController;
use App\Http\Controllers\Identity\PersonDocumentController;
use App\Http\Controllers\Identity\PersonProfileController;
use App\Http\Controllers\Platform\AttachmentController;
use App\Http\Controllers\Platform\AttachmentThumbnailController;
use App\Http\Controllers\Platform\AuditLogController;
use App\Http\Controllers\Platform\CalendarController;
use App\Http\Controllers\Platform\DirectionDashboardController;
use App\Http\Controllers\Platform\HealthController;
use App\Http\Controllers\Platform\InternalDocumentController;
use App\Http\Controllers\Platform\ListExportController;
use App\Http\Controllers\Platform\ListingController;
use App\Http\Controllers\Platform\NotificationController;
use App\Http\Controllers\Platform\SavedFilterController;
use App\Http\Controllers\Platform\SearchController;
use App\Http\Controllers\Platform\SettingController;
use App\Http\Controllers\Work\CompanyPriorityController;
use App\Http\Controllers\Work\DeliverableController;
use App\Http\Controllers\Work\ObjectiveController;
use App\Http\Controllers\Work\ProjectController;
use App\Http\Controllers\Work\TaskController;
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
    Route::get('/', HomeController::class)->name('home');
    Route::get('/rapports/quotidiens/aujourdhui', [DailyReportController::class, 'create'])
        ->middleware('permission:rapport_quotidien.creer')
        ->name('daily-reports.today');
    Route::get('/rapports', DailyReportAnalyticsController::class)
        ->middleware('permission:rapport_quotidien.consulter')
        ->name('daily-reports.index');
    Route::get('/blocages', [BlockerController::class, 'index'])->middleware('permission:blocage.consulter')->name('blockers.index');
    Route::post('/blocages', [BlockerController::class, 'store'])->middleware('permission:blocage.creer')->name('blockers.store');
    Route::patch('/blocages/{blocker}/etat', [BlockerController::class, 'transition'])->middleware('permission:blocage.gerer')->name('blockers.transition');
    Route::post('/rapports/quotidiens', [DailyReportController::class, 'store'])
        ->middleware('permission:rapport_quotidien.creer')
        ->name('daily-reports.store');
    Route::post('/rapports/{dailyReport}/demandes-tache', [TaskRequestController::class, 'store'])
        ->middleware('permission:rapport_quotidien.creer')
        ->name('task-requests.store');
    Route::patch('/demandes-tache/{taskRequest}/traiter', [TaskRequestController::class, 'process'])
        ->middleware('permission:tache.gerer')
        ->name('task-requests.process');
    Route::middleware('permission:rapport_quotidien.valider')->group(function (): void {
        Route::get('/rapports/validation', [DailyReportReviewController::class, 'index'])->name('daily-report-reviews.index');
        Route::get('/rapports/{dailyReport}/validation', [DailyReportReviewController::class, 'show'])->name('daily-report-reviews.show');
        Route::post('/rapports/{dailyReport}/commentaires', [DailyReportReviewController::class, 'comment'])->name('daily-report-reviews.comments.store');
        Route::patch('/rapports/{dailyReport}/valider', [DailyReportReviewController::class, 'validateReport'])->name('daily-report-reviews.validate');
        Route::patch('/rapports/{dailyReport}/retourner', [DailyReportReviewController::class, 'returnReport'])->name('daily-report-reviews.return');
    });
    Route::middleware('permission:revue_hebdomadaire.consulter')->group(function (): void {
        Route::get('/revues-hebdomadaires', [WeeklyReviewController::class, 'index'])->name('weekly-reviews.index');
        Route::get('/revues-hebdomadaires/{weeklyReview}', [WeeklyReviewController::class, 'show'])->name('weekly-reviews.show');
        Route::post('/revues-hebdomadaires/{weeklyReview}/commentaires', [WeeklyReviewController::class, 'comment'])->name('weekly-reviews.comments.store');
        Route::patch('/revues-hebdomadaires/{weeklyReview}/valider', [WeeklyReviewController::class, 'validateReview'])->name('weekly-reviews.validate');
    });
    Route::middleware('permission:revue_hebdomadaire.gerer')->group(function (): void {
        Route::post('/revues-hebdomadaires', [WeeklyReviewController::class, 'store'])->name('weekly-reviews.store');
        Route::post('/revues-hebdomadaires/{weeklyReview}/objectifs', [WeeklyReviewController::class, 'recordObjective'])->name('weekly-reviews.objectives.store');
        Route::patch('/revues-hebdomadaires/{weeklyReview}/soumettre', [WeeklyReviewController::class, 'submit'])->name('weekly-reviews.submit');
        Route::post('/revues-hebdomadaires/{weeklyReview}/plans-accompagnement', [ImprovementPlanController::class, 'store'])->name('improvement-plans.store');
        Route::patch('/plans-accompagnement/{improvementPlan}/terminer', [ImprovementPlanController::class, 'close'])->name('improvement-plans.close');
    });
    Route::middleware('permission:revue_hebdomadaire.consulter')->group(function (): void {
        Route::get('/plans-accompagnement', [ImprovementPlanController::class, 'index'])->name('improvement-plans.index');
        Route::get('/plans-accompagnement/{improvementPlan}', [ImprovementPlanController::class, 'show'])->name('improvement-plans.show');
    });
    Route::middleware('permission:stagiaire.consulter')->group(function (): void {
        Route::get('/stages/fiches-entree', [InternshipIntakeController::class, 'index'])->name('internship-intakes.index');
        Route::get('/stages/fiches-entree/{internshipIntakeForm}', [InternshipIntakeController::class, 'show'])->name('internship-intakes.show');
        Route::get('/stages', [InternshipController::class, 'index'])->name('internships.index');
        Route::get('/creneaux-suivi', [TutorSupportSlotController::class, 'index'])->name('support-slots.index');
        Route::post('/creneaux-suivi', [TutorSupportSlotController::class, 'store'])->name('support-slots.store');
        // Contrainte numérique : sans elle, `/stages/tuteurs` serait capturé par ce paramètre.
        Route::get('/stages/{internship}', [InternshipController::class, 'show'])
            ->whereNumber('internship')
            ->name('internships.show');
    });
    Route::middleware('permission:stagiaire.gerer')->group(function (): void {
        Route::post('/stages/fiches-entree', [InternshipIntakeController::class, 'store'])->name('internship-intakes.store');
        Route::patch('/stages/fiches-entree/{internshipIntakeForm}/soumettre', [InternshipIntakeController::class, 'submit'])->name('internship-intakes.submit');
        Route::patch('/stages/fiches-entree/{internshipIntakeForm}/decision', [InternshipIntakeController::class, 'decide'])->name('internship-intakes.decide');
        Route::post('/stages/stagiaires/{user}/activer', [InternshipIntakeController::class, 'activate'])->name('interns.activate');
        // Hors de `/stages/…` volontairement : cet écran porte sur les tuteurs, pas sur un stage,
        // et aucun segment ne peut ainsi entrer en collision avec `/stages/{internship}`.
        Route::get('/tuteurs/charge', TutorCapacityController::class)->name('tutors.capacity');
        Route::put('/stages/{internship}/plan', [InternshipController::class, 'savePlan'])->name('internships.plan.save');
        Route::post('/stages/{internship}/evaluations', [InternshipController::class, 'recordEvaluation'])->name('internships.evaluations.store');
        Route::patch('/stages/{internship}/evaluations/{evaluation}/valider', [InternshipController::class, 'validateEvaluation'])->name('internships.evaluations.validate');
        Route::patch('/stages/{internship}/sortie', [InternshipController::class, 'exit'])->name('internships.exit');
    });
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');
    Route::middleware('permission:absence.consulter')->group(function (): void {
        Route::get('/absences', [AbsenceController::class, 'index'])->name('absences.index');
        Route::get('/absences/{absence}', [AbsenceController::class, 'show'])->name('absences.show');
        Route::post('/absences', [AbsenceController::class, 'store'])->name('absences.store');
        Route::patch('/absences/{absence}/approuver', [AbsenceController::class, 'approve'])->name('absences.approve');
        Route::patch('/absences/{absence}/refuser', [AbsenceController::class, 'refuse'])->name('absences.refuse');
        Route::patch('/absences/{absence}/annuler', [AbsenceController::class, 'cancel'])->name('absences.cancel');
    });
    Route::patch('/notifications/{notification}/lue', [NotificationController::class, 'read'])
        ->name('notifications.read');
    Route::patch('/notifications/tout-lu', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');
    Route::middleware('permission:calendrier.gerer')->group(function (): void {
        Route::get('/calendrier', [CalendarController::class, 'index'])
            ->name('calendar.index');
        Route::post('/calendrier/jours-feries', [CalendarController::class, 'store'])
            ->name('holidays.store');
        Route::patch('/calendrier/jours-feries/{holiday}', [CalendarController::class, 'update'])
            ->name('holidays.update');
        Route::patch('/calendrier/jours-feries/{holiday}/desactiver', [CalendarController::class, 'deactivate'])
            ->name('holidays.deactivate');
        Route::patch('/calendrier/jours-feries/{holiday}/reactiver', [CalendarController::class, 'reactivate'])
            ->name('holidays.reactivate');
    });
    Route::get('/documents-internes', [InternalDocumentController::class, 'index'])
        ->middleware('permission:document_interne.consulter')
        ->name('internal-documents.index');
    Route::get('/documents-internes/{internalDocument}', [InternalDocumentController::class, 'show'])
        ->middleware('permission:document_interne.consulter')
        ->name('internal-documents.show');
    Route::post('/documents-internes', [InternalDocumentController::class, 'store'])
        ->middleware('permission:document_interne.gerer')
        ->name('internal-documents.store');
    Route::post('/documents-internes/{internalDocument}/versions', [InternalDocumentController::class, 'storeVersion'])
        ->middleware('permission:document_interne.gerer')
        ->name('internal-documents.versions.store');
    Route::post('/documents-internes/{internalDocument}/accepter', [InternalDocumentController::class, 'acknowledge'])
        ->middleware('permission:document_interne.consulter')
        ->name('internal-documents.acknowledge');
    Route::get('/documents-internes/{internalDocument}/acceptations', [InternalDocumentController::class, 'acknowledgements'])
        ->middleware('permission:document_interne.gerer')
        ->name('internal-documents.acknowledgements');
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
        Route::get('/categories-depense', [ExpenseCategoryController::class, 'index'])
            ->name('expense-categories.index');
        Route::post('/categories-depense', [ExpenseCategoryController::class, 'store'])
            ->name('expense-categories.store');
        Route::patch('/categories-depense/{expenseCategory}', [ExpenseCategoryController::class, 'update'])
            ->name('expense-categories.update');
        Route::patch('/categories-depense/{expenseCategory}/desactiver', [ExpenseCategoryController::class, 'deactivate'])
            ->name('expense-categories.deactivate');
        Route::patch('/categories-depense/{expenseCategory}/reactiver', [ExpenseCategoryController::class, 'reactivate'])
            ->name('expense-categories.reactivate');
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

    Route::middleware('permission:objectif_entreprise.consulter')->group(function (): void {
        Route::get('/priorites-entreprise', [CompanyPriorityController::class, 'index'])->name('company-priorities.index');
        Route::post('/priorites-entreprise', [CompanyPriorityController::class, 'store'])->middleware('permission:objectif_entreprise.gerer')->name('company-priorities.store');
        Route::patch('/priorites-entreprise/{companyPriority}', [CompanyPriorityController::class, 'update'])->middleware('permission:objectif_entreprise.gerer')->name('company-priorities.update');
        Route::patch('/priorites-entreprise/{companyPriority}/annuler', [CompanyPriorityController::class, 'cancel'])->middleware('permission:objectif_entreprise.gerer')->name('company-priorities.cancel');
    });

    Route::middleware('permission:objectif_individuel.consulter')->group(function (): void {
        Route::get('/objectifs', [ObjectiveController::class, 'index'])->name('objectives.index');
        Route::get('/objectifs/calendrier', [ObjectiveController::class, 'calendar'])->name('objectives.calendar');
        Route::get('/objectifs/synthese', [ObjectiveController::class, 'summary'])->name('objectives.summary');
        Route::post('/objectifs', [ObjectiveController::class, 'store'])->middleware('permission:objectif_individuel.gerer')->name('objectives.store');
        Route::get('/objectifs/{objective}', [ObjectiveController::class, 'show'])->name('objectives.show');
        Route::patch('/objectifs/{objective}', [ObjectiveController::class, 'update'])->middleware('permission:objectif_individuel.gerer')->name('objectives.update');
        Route::patch('/objectifs/{objective}/valider', [ObjectiveController::class, 'validateObjective'])->middleware('permission:objectif.valider')->name('objectives.validate');
        Route::patch('/objectifs/{objective}/etat', [ObjectiveController::class, 'transition'])->middleware('permission:objectif_individuel.gerer')->name('objectives.transition');
        Route::post('/objectifs/{objective}/copier', [ObjectiveController::class, 'copy'])->middleware('permission:objectif_individuel.gerer')->name('objectives.copy');
        Route::post('/objectifs/{objective}/commentaires', [ObjectiveController::class, 'comment'])->middleware('permission:objectif_individuel.gerer')->name('objectives.comments.store');
    });

    Route::middleware('permission:projet.consulter')->group(function (): void {
        Route::get('/projets', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('/projets', [ProjectController::class, 'store'])->middleware('permission:projet.gerer')->name('projects.store');
        Route::get('/projets/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::get('/projets/{project}/budget', [ProjectController::class, 'budget'])->middleware('permission:projet.budget.consulter')->name('projects.budget');
        Route::patch('/projets/{project}/etat', [ProjectController::class, 'transition'])->name('projects.transition');
        Route::patch('/projets/{project}/membres', [ProjectController::class, 'members'])->name('projects.members.update');
        Route::post('/projets/{project}/commentaires', [ProjectController::class, 'comment'])->name('projects.comments.store');
        Route::post('/projets/{project}/liens', [ProjectController::class, 'link'])->name('projects.links.store');
        Route::post('/projets/{project}/pieces-jointes', [ProjectController::class, 'attach'])->name('projects.attachments.store');
    });

    Route::middleware('permission:tache.consulter')->group(function (): void {
        Route::get('/taches', [TaskController::class, 'index'])->name('tasks.index');
        Route::get('/taches/aujourdhui', [TaskController::class, 'today'])->name('tasks.today');
        Route::post('/taches', [TaskController::class, 'store'])->middleware('permission:tache.gerer')->name('tasks.store');
        Route::get('/taches/{task}', [TaskController::class, 'show'])->name('tasks.show');
        Route::post('/taches/{task}/commentaires', [TaskController::class, 'comment'])->middleware('permission:tache.gerer')->name('tasks.comments.store');
        Route::post('/taches/{task}/liens', [TaskController::class, 'link'])->middleware('permission:tache.gerer')->name('tasks.links.store');
        Route::post('/taches/{task}/pieces-jointes', [TaskController::class, 'attach'])->middleware('permission:tache.gerer')->name('tasks.attachments.store');
    });

    Route::middleware('permission:livrable.consulter')->group(function (): void {
        Route::get('/livrables', [DeliverableController::class, 'index'])->name('deliverables.index');
        Route::post('/livrables', [DeliverableController::class, 'store'])->middleware('permission:livrable.gerer')->name('deliverables.store');
        Route::patch('/livrables/{deliverable}/etat', [DeliverableController::class, 'transition'])->middleware('permission:livrable.gerer')->name('deliverables.transition');
    });

    Route::middleware('permission:compte_financier.consulter')->group(function (): void {
        Route::get('/finances/comptes', [FinancialAccountController::class, 'index'])
            ->name('financial-accounts.index');
        Route::post('/finances/comptes', [FinancialAccountController::class, 'store'])
            ->middleware('permission:compte_financier.gerer')
            ->name('financial-accounts.store');
        Route::patch('/finances/comptes/{account}/desactiver', [FinancialAccountController::class, 'deactivate'])
            ->middleware('permission:compte_financier.gerer')
            ->name('financial-accounts.deactivate');
    });

    Route::middleware('permission:charge_fixe.consulter')->group(function (): void {
        Route::get('/finances/charges-fixes', [FixedChargeController::class, 'index'])
            ->name('fixed-charges.index');
        Route::post('/finances/charges-fixes/apercu', [FixedChargeController::class, 'preview'])
            ->middleware('permission:charge_fixe.gerer')
            ->name('fixed-charges.preview');
        Route::post('/finances/charges-fixes', [FixedChargeController::class, 'store'])
            ->middleware('permission:charge_fixe.gerer')
            ->name('fixed-charges.store');
        Route::patch('/finances/charges-fixes/{fixedCharge}', [FixedChargeController::class, 'update'])
            ->middleware('permission:charge_fixe.gerer')
            ->name('fixed-charges.update');
    });

    Route::middleware('permission:client.consulter')->group(function (): void {
        Route::get('/finances/clients', [ClientController::class, 'index'])
            ->name('clients.index');
        Route::post('/finances/clients', [ClientController::class, 'store'])
            ->middleware('permission:client.gerer')
            ->name('clients.store');
        Route::patch('/finances/clients/{client}', [ClientController::class, 'update'])
            ->middleware('permission:client.gerer')
            ->name('clients.update');
        Route::get('/finances/contrats', [ContractController::class, 'index'])
            ->name('contracts.index');
        Route::post('/finances/contrats', [ContractController::class, 'store'])
            ->middleware('permission:client.gerer')
            ->name('contracts.store');
        Route::patch('/finances/contrats/{contract}', [ContractController::class, 'update'])
            ->middleware('permission:client.gerer')
            ->name('contracts.update');
        Route::post('/finances/contrats/{contract}/cloturer', [ContractController::class, 'close'])
            ->middleware('permission:client.gerer')
            ->name('contracts.close');
    });

    Route::middleware('permission:budget_financier.consulter')->group(function (): void {
        Route::get('/finances/budgets-mensuels', [MonthlyBudgetController::class, 'index'])->name('monthly-budgets.index');
        Route::post('/finances/budgets-mensuels', [MonthlyBudgetController::class, 'store'])
            ->middleware('permission:budget_financier.gerer')->name('monthly-budgets.store');
    });

    Route::middleware('permission:facture.consulter')->group(function (): void {
        Route::get('/finances/factures', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::post('/finances/factures', [InvoiceController::class, 'store'])
            ->middleware('permission:facture.gerer')->name('invoices.store');
        Route::patch('/finances/factures/{invoice}/annuler', [InvoiceController::class, 'cancel'])
            ->middleware('permission:facture.gerer')->name('invoices.cancel');
    });

    Route::middleware('permission:encaissement.consulter')->group(function (): void {
        Route::get('/finances/encaissements', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/finances/encaissements', [PaymentController::class, 'store'])
            ->middleware('permission:encaissement.gerer')->name('payments.store');
        Route::post('/finances/encaissements/{payment}/corriger', [PaymentController::class, 'correct'])
            ->middleware('permission:encaissement.gerer')->name('payments.correct');
        Route::post('/finances/encaissements/{payment}/annuler', [PaymentController::class, 'cancel'])
            ->middleware('permission:encaissement.gerer')->name('payments.cancel');
    });

    Route::middleware('permission:depense.payer')->group(function (): void {
        Route::get('/finances/paiements-depenses', [ExpensePaymentController::class, 'index'])->name('expense-payments.index');
        Route::post('/finances/paiements-depenses/{expense}/payer', [ExpensePaymentController::class, 'pay'])->name('expense-payments.pay');
        Route::post('/finances/paiements-depenses/{expense}/annuler', [ExpensePaymentController::class, 'cancel'])->name('expense-payments.cancel');
    });

    Route::middleware('permission:part.consulter')->group(function (): void {
        Route::get('/finances/parts', [ShareEntitlementController::class, 'index'])->name('shares.index');
        Route::get('/finances/parts/{shareEntitlement}', [ShareEntitlementController::class, 'show'])->name('shares.show');
        Route::post('/finances/parts/{shareEntitlement}/demander-versement', [ShareEntitlementController::class, 'requestPayment'])->name('shares.request-payment');
    });

    Route::middleware('permission:reserve.consulter')->group(function (): void {
        Route::get('/finances/reserve', [ReserveController::class, 'index'])->name('reserve.index');
        Route::post('/finances/reserve/utilisations', [ReserveController::class, 'requestUsage'])->name('reserve.usages.store');
        Route::patch('/finances/reserve/mouvements/{reserveMovement}/approuver', [ReserveController::class, 'approve'])
            ->middleware('permission:reserve.gerer')->name('reserve.usages.approve');
    });

    Route::middleware('permission:rapprochement.consulter')->group(function (): void {
        Route::get('/finances/rapprochements', [ReconciliationController::class, 'index'])->name('reconciliations.index');
        Route::post('/finances/rapprochements', [ReconciliationController::class, 'store'])
            ->middleware('permission:rapprochement.preparer')->name('reconciliations.store');
        Route::patch('/finances/rapprochements/{reconciliation}/valider', [ReconciliationController::class, 'validateReconciliation'])
            ->middleware('permission:rapprochement.controler')->name('reconciliations.validate');
        Route::post('/finances/rapprochements/{reconciliation}/corriger', [ReconciliationController::class, 'correct'])
            ->middleware('permission:rapprochement.preparer')->name('reconciliations.correct');
    });

    Route::middleware('permission:rapport_financier.consulter')->group(function (): void {
        Route::get('/finances/rapports-mensuels', [MonthlyReportController::class, 'index'])->name('financial-reports.index');
        Route::post('/finances/rapports-mensuels', [MonthlyReportController::class, 'store'])
            ->middleware('permission:rapport_financier.preparer')->name('financial-reports.store');
        Route::patch('/finances/rapports-mensuels/{monthlyReport}/controler', [MonthlyReportController::class, 'control'])
            ->middleware('permission:rapport_financier.controler')->name('financial-reports.control');
        Route::patch('/finances/rapports-mensuels/{monthlyReport}/valider', [MonthlyReportController::class, 'validateReport'])
            ->middleware('permission:rapport_financier.valider')->name('financial-reports.validate');
        Route::patch('/finances/rapports-mensuels/{monthlyReport}/reouvrir', [MonthlyReportController::class, 'reopen'])
            ->middleware('permission:rapport_financier.valider')->name('financial-reports.reopen');

        // Plan correctif exigé par le niveau d'alerte orange (story 9.1, AC 11, AC 14 à 18).
        // Il reste consultable après le retour au vert : la consultation ne dépend pas du niveau.
        Route::get('/finances/plans-correctifs', [CorrectionPlanController::class, 'index'])->name('correction-plans.index');
        Route::get('/finances/plans-correctifs/nouveau', [CorrectionPlanController::class, 'create'])
            ->middleware('permission:rapport_financier.valider')->name('correction-plans.create');
        Route::post('/finances/plans-correctifs', [CorrectionPlanController::class, 'store'])
            ->middleware('permission:rapport_financier.valider')->name('correction-plans.store');
        Route::patch('/finances/plans-correctifs/{correctionPlan}/valider', [CorrectionPlanController::class, 'validatePlan'])
            ->middleware('permission:rapport_financier.valider')->name('correction-plans.validate');
        Route::post('/finances/plans-correctifs/{correctionPlan}/reviser', [CorrectionPlanController::class, 'revise'])
            ->middleware('permission:rapport_financier.valider')->name('correction-plans.revise');
    });

    // Tableau de bord financier : `finance` et `direction` uniquement (AC 22). Tout autre rôle
    // reçoit 403 sur l'URL directe, sans contenu partiel ni redirection (SOC-01).
    Route::get('/finances/tableau-de-bord', FinancialDashboardController::class)
        ->middleware('permission:tableau_bord_financier.consulter')
        ->name('dashboard.finance');

    // Tableau de bord direction consolidé (AC 25 à 31). Remplace la fixture d'autorisation
    // `testing.authorization.dashboard-global.view`.
    Route::get('/tableau-de-bord/direction', DirectionDashboardController::class)
        ->middleware('permission:tableau_bord_global.consulter')
        ->name('dashboard.direction');

    // Story 10.1 — recherche transverse, listes filtrables et exports CSV.
    // Remplace la fixture `testing.authorization.search`.
    Route::get('/recherche', SearchController::class)
        ->middleware('permission:recherche.utiliser')
        ->name('search.index');

    // Une seule route de liste et une seule route d'export pour les quatre listes principales :
    // la permission effective est celle de la liste demandée, vérifiée par `ListingRequest`.
    Route::get('/listes/{liste}', ListingController::class)->name('listing.index');
    Route::get('/listes/{liste}/export', ListExportController::class)
        ->middleware('permission:export.creer')
        ->name('exports.create');

    Route::middleware('permission:tableau_bord_global.consulter')->group(function (): void {
        Route::post('/filtres-enregistres', [SavedFilterController::class, 'store'])->name('saved-filters.store');
        Route::patch('/filtres-enregistres/{savedFilter}/retirer', [SavedFilterController::class, 'deactivate'])
            ->name('saved-filters.deactivate');
    });

    // Expense routes - store is socle auth (all active authenticated users)
    Route::get('/depenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
    Route::post('/depenses', [ExpenseController::class, 'store'])->name('expenses.store');

    Route::middleware('permission:depense.approuver')->group(function (): void {
        Route::get('/depenses/approbations', [ExpenseApprovalController::class, 'index'])
            ->name('expenses.approvals.index');
        Route::get('/depenses/{expense}/decision', [ExpenseApprovalController::class, 'show'])
            ->name('expenses.approvals.show');
        Route::get('/depenses/{expense}/decision/justificatif', [ExpenseApprovalController::class, 'attachment'])
            ->name('expenses.approvals.attachment');
        Route::patch('/depenses/{expense}/approuver', [ExpenseApprovalController::class, 'approve'])
            ->name('expenses.approve');
        Route::patch('/depenses/{expense}/refuser', [ExpenseApprovalController::class, 'refuse'])
            ->name('expenses.refuse');
    });

    // Other expense routes require depense.consulter permission with fine-grained scoping in policy
    Route::middleware('permission:depense.consulter')->group(function (): void {
        Route::get('/depenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('/depenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
        Route::patch('/depenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::patch('/depenses/{expense}/annuler', [ExpenseController::class, 'cancel'])->name('expenses.cancel');
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
