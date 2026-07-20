<?php

$roles = ['super_admin', 'direction', 'finance', 'tuteur', 'employe', 'stagiaire'];
$statuses = static function (array $allowedRoles) use ($roles): array {
    $matrix = [];

    foreach ($roles as $role) {
        $matrix[$role] = in_array($role, $allowedRoles, true) ? 204 : 403;
    }

    return $matrix;
};

return [
    'roles' => $roles,
    'prd_source' => [
        'reference' => 'docs/prd.md#43-matrice-daccès',
        'reviewed_on' => '2026-07-20',
        'reviewed_for_milestone' => 'Epic 2',
        'quality_gate_runbook' => 'docs/ops/authorization-quality-gate.md',
    ],
    'access_scopes' => [
        'unauthenticated' => ['status' => 302, 'redirect_route' => 'login'],
        'inactive_account' => ['status' => 302, 'redirect_route' => 'login'],
        'password_change_required' => [
            'status' => 302,
            'redirect_route' => 'password.change.edit',
            'except' => ['password.change.edit', 'password.change.update', 'logout'],
        ],
    ],
    // Routes de socle accessibles à tout compte authentifié actif, sans permission métier.
    'authentication_routes' => [
        'home' => ['method' => 'GET', 'path' => '/'],
        'password.change.edit' => ['method' => 'GET', 'path' => '/mot-de-passe/modifier'],
        'password.change.update' => ['method' => 'PATCH', 'path' => '/mot-de-passe'],
        'logout' => ['method' => 'POST', 'path' => '/deconnexion'],
    ],
    'routes' => [
        'accounts.index' => [
            'path' => '/comptes',
            'permission' => 'compte.gerer|compte.technique.gerer',
            'statuses' => $statuses(['super_admin', 'direction']),
        ],
        'accounts.store' => [
            'method' => 'POST',
            'path' => '/comptes',
            'permission' => 'compte.gerer|compte.technique.gerer',
            'statuses' => $statuses(['super_admin', 'direction']),
        ],
        'accounts.roles.sync' => [
            'method' => 'PATCH',
            'path' => '/comptes/{user}/roles',
            'permission' => 'compte.gerer|compte.technique.gerer',
            'statuses' => $statuses(['super_admin', 'direction']),
        ],
        'accounts.archive' => [
            'method' => 'PATCH',
            'path' => '/comptes/{user}/archiver',
            'permission' => 'compte.gerer|compte.technique.gerer',
            'statuses' => $statuses(['super_admin', 'direction']),
        ],
        'accounts.password-reinitialization.initiate' => [
            'method' => 'POST',
            'path' => '/comptes/{user}/reinitialisation/initier',
            'permission' => 'compte.gerer|compte.technique.gerer',
            'statuses' => $statuses(['super_admin', 'direction']),
        ],
        'accounts.password-reinitialization.confirm' => [
            'method' => 'POST',
            'path' => '/comptes/{user}/reinitialisation/confirmer',
            'permission' => 'compte.gerer|compte.technique.gerer',
            'statuses' => $statuses(['super_admin', 'direction']),
        ],
        'login-attempts.index' => [
            'path' => '/connexions',
            'permission' => 'connexion.consulter',
            'statuses' => $statuses(['direction']),
        ],
        'audit.index' => [
            'path' => '/journal-audit',
            'permission' => 'audit.consulter',
            'statuses' => $statuses(['direction']),
        ],
        'audit.export' => [
            'path' => '/journal-audit/export',
            'permission' => 'audit.consulter',
            'statuses' => $statuses(['direction']),
        ],
    ],
    // Contrats provisoires : chaque fixture disparaît dès que sa route réelle de remplacement existe.
    'fixtures' => [
        'testing.authorization.dashboard.view' => [
            'path' => '/__test/authorization/dashboard',
            'permission' => 'tableau_bord.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
            'replacement' => ['route' => 'dashboard.index', 'story' => '5.8'],
        ],
        'testing.authorization.dashboard-global.view' => [
            'path' => '/__test/authorization/dashboard-global',
            'permission' => 'tableau_bord_global.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur']),
            'replacement' => ['route' => 'dashboard.direction', 'story' => '9.5'],
        ],
        'testing.authorization.account.manage' => [
            'path' => '/__test/authorization/accounts',
            'permission' => 'compte.gerer|compte.technique.gerer',
            'statuses' => $statuses(['super_admin', 'direction']),
            'replacement' => ['route' => 'authorization.accounts.index', 'story' => '10.4'],
        ],
        'testing.authorization.role.manage' => [
            'path' => '/__test/authorization/roles',
            'permission' => 'role.gerer',
            'statuses' => $statuses(['super_admin', 'direction']),
            'replacement' => ['route' => 'authorization.roles.index', 'story' => '10.4'],
        ],
        'testing.authorization.setting.manage' => [
            'path' => '/__test/authorization/settings',
            'permission' => 'parametre.gerer',
            'statuses' => $statuses(['super_admin', 'direction']),
            'replacement' => ['route' => 'settings.index', 'story' => '3.4'],
        ],
        'testing.authorization.objective-company.view' => [
            'path' => '/__test/authorization/company-objectives',
            'permission' => 'objectif_entreprise.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
            'replacement' => ['route' => 'company-objectives.index', 'story' => '5.1'],
        ],
        'testing.authorization.objective-individual.view' => [
            'path' => '/__test/authorization/individual-objectives',
            'permission' => 'objectif_individuel.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
            'replacement' => ['route' => 'objectives.index', 'story' => '5.2'],
        ],
        'testing.authorization.project.view' => [
            'path' => '/__test/authorization/projects',
            'permission' => 'projet.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
            'replacement' => ['route' => 'projects.index', 'story' => '5.5'],
        ],
        'testing.authorization.daily-report.view' => [
            'path' => '/__test/authorization/daily-reports',
            'permission' => 'rapport_quotidien.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
            'replacement' => ['route' => 'daily-reports.index', 'story' => '6.4'],
        ],
        'testing.authorization.weekly-review.view' => [
            'path' => '/__test/authorization/weekly-reviews',
            'permission' => 'revue_hebdomadaire.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
            'replacement' => ['route' => 'weekly-reviews.index', 'story' => '7.1'],
        ],
        'testing.authorization.blocker.view' => [
            'path' => '/__test/authorization/blockers',
            'permission' => 'blocage.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
            'replacement' => ['route' => 'blockers.index', 'story' => '6.6'],
        ],
        'testing.authorization.intern.view' => [
            'path' => '/__test/authorization/interns',
            'permission' => 'stagiaire.consulter',
            'statuses' => $statuses(['direction', 'tuteur', 'stagiaire']),
            'replacement' => ['route' => 'interns.index', 'story' => '7.3'],
        ],
        'testing.authorization.absence.view' => [
            'path' => '/__test/authorization/absences',
            'permission' => 'absence.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
            'replacement' => ['route' => 'absences.index', 'story' => '4.2'],
        ],
        'testing.authorization.internal-document.view' => [
            'path' => '/__test/authorization/internal-documents',
            'permission' => 'document_interne.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
            'replacement' => ['route' => 'internal-documents.index', 'story' => '3.8'],
        ],
        'testing.authorization.expense.view' => [
            'path' => '/__test/authorization/expenses',
            'permission' => 'depense.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
            'replacement' => ['route' => 'expenses.index', 'story' => '4.4'],
        ],
        'testing.authorization.finance-entry.view' => [
            'path' => '/__test/authorization/finance-entries',
            'permission' => 'finance.ecriture.consulter',
            'statuses' => $statuses(['direction', 'finance', 'employe', 'stagiaire']),
            'replacement' => ['route' => 'finance.entries.index', 'story' => '8.1'],
        ],
        'testing.authorization.client.view' => [
            'path' => '/__test/authorization/clients',
            'permission' => 'client.consulter',
            'statuses' => $statuses(['direction', 'finance']),
            'replacement' => ['route' => 'clients.index', 'story' => '8.3'],
        ],
        'testing.authorization.share.view' => [
            'path' => '/__test/authorization/shares',
            'permission' => 'part.consulter',
            'statuses' => $statuses(['direction', 'finance', 'employe']),
            'replacement' => ['route' => 'shares.index', 'story' => '8.7'],
        ],
        'testing.authorization.reserve.view' => [
            'path' => '/__test/authorization/reserve',
            'permission' => 'reserve.consulter',
            'statuses' => $statuses(['direction', 'finance']),
            'replacement' => ['route' => 'reserve.index', 'story' => '8.11'],
        ],
        'testing.authorization.financial-report.view' => [
            'path' => '/__test/authorization/financial-reports',
            'permission' => 'rapport_financier.consulter',
            'statuses' => $statuses(['direction', 'finance']),
            'replacement' => ['route' => 'financial-reports.index', 'story' => '8.13'],
        ],
        'testing.authorization.search' => [
            'path' => '/__test/authorization/search',
            'permission' => 'recherche.utiliser',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
            'replacement' => ['route' => 'search.index', 'story' => '10.1'],
        ],
        'testing.authorization.expense.approve' => [
            'path' => '/__test/authorization/expenses/approve',
            'permission' => 'depense.approuver',
            'statuses' => $statuses(['direction']),
            'replacement' => ['route' => 'expenses.approvals.index', 'story' => '4.5'],
        ],
        'testing.authorization.objective.validate' => [
            'path' => '/__test/authorization/objectives/validate',
            'permission' => 'objectif.valider',
            'statuses' => $statuses(['direction']),
            'replacement' => ['route' => 'objectives.validate', 'story' => '5.3'],
        ],
        'testing.authorization.financial-report.validate' => [
            'path' => '/__test/authorization/financial-reports/validate',
            'permission' => 'rapport_financier.valider',
            'statuses' => $statuses(['direction']),
            'replacement' => ['route' => 'financial-reports.validate', 'story' => '8.13'],
        ],
    ],
];
