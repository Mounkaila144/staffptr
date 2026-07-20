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
        'login-attempts.index' => [
            'path' => '/connexions',
            'permission' => 'connexion.consulter',
            'statuses' => $statuses(['direction']),
        ],
        'testing.authorization.dashboard.view' => [
            'path' => '/__test/authorization/dashboard',
            'permission' => 'tableau_bord.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
        ],
        'testing.authorization.dashboard-global.view' => [
            'path' => '/__test/authorization/dashboard-global',
            'permission' => 'tableau_bord_global.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur']),
        ],
        'testing.authorization.account.manage' => [
            'path' => '/__test/authorization/accounts',
            'permission' => 'compte.gerer|compte.technique.gerer',
            'statuses' => $statuses(['super_admin', 'direction']),
        ],
        'testing.authorization.role.manage' => [
            'path' => '/__test/authorization/roles',
            'permission' => 'role.gerer',
            'statuses' => $statuses(['super_admin', 'direction']),
        ],
        'testing.authorization.setting.manage' => [
            'path' => '/__test/authorization/settings',
            'permission' => 'parametre.gerer',
            'statuses' => $statuses(['super_admin', 'direction']),
        ],
        'testing.authorization.objective-company.view' => [
            'path' => '/__test/authorization/company-objectives',
            'permission' => 'objectif_entreprise.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
        ],
        'testing.authorization.objective-individual.view' => [
            'path' => '/__test/authorization/individual-objectives',
            'permission' => 'objectif_individuel.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
        ],
        'testing.authorization.project.view' => [
            'path' => '/__test/authorization/projects',
            'permission' => 'projet.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
        ],
        'testing.authorization.daily-report.view' => [
            'path' => '/__test/authorization/daily-reports',
            'permission' => 'rapport_quotidien.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
        ],
        'testing.authorization.weekly-review.view' => [
            'path' => '/__test/authorization/weekly-reviews',
            'permission' => 'revue_hebdomadaire.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
        ],
        'testing.authorization.blocker.view' => [
            'path' => '/__test/authorization/blockers',
            'permission' => 'blocage.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
        ],
        'testing.authorization.intern.view' => [
            'path' => '/__test/authorization/interns',
            'permission' => 'stagiaire.consulter',
            'statuses' => $statuses(['direction', 'tuteur', 'stagiaire']),
        ],
        'testing.authorization.absence.view' => [
            'path' => '/__test/authorization/absences',
            'permission' => 'absence.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
        ],
        'testing.authorization.internal-document.view' => [
            'path' => '/__test/authorization/internal-documents',
            'permission' => 'document_interne.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
        ],
        'testing.authorization.expense.view' => [
            'path' => '/__test/authorization/expenses',
            'permission' => 'depense.consulter',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
        ],
        'testing.authorization.finance-entry.view' => [
            'path' => '/__test/authorization/finance-entries',
            'permission' => 'finance.ecriture.consulter',
            'statuses' => $statuses(['direction', 'finance', 'employe', 'stagiaire']),
        ],
        'testing.authorization.client.view' => [
            'path' => '/__test/authorization/clients',
            'permission' => 'client.consulter',
            'statuses' => $statuses(['direction', 'finance']),
        ],
        'testing.authorization.share.view' => [
            'path' => '/__test/authorization/shares',
            'permission' => 'part.consulter',
            'statuses' => $statuses(['direction', 'finance', 'employe']),
        ],
        'testing.authorization.reserve.view' => [
            'path' => '/__test/authorization/reserve',
            'permission' => 'reserve.consulter',
            'statuses' => $statuses(['direction', 'finance']),
        ],
        'testing.authorization.financial-report.view' => [
            'path' => '/__test/authorization/financial-reports',
            'permission' => 'rapport_financier.consulter',
            'statuses' => $statuses(['direction', 'finance']),
        ],
        'testing.authorization.audit.view' => [
            'path' => '/__test/authorization/audit',
            'permission' => 'audit.consulter',
            'statuses' => $statuses(['direction']),
        ],
        'testing.authorization.search' => [
            'path' => '/__test/authorization/search',
            'permission' => 'recherche.utiliser',
            'statuses' => $statuses(['direction', 'finance', 'tuteur', 'employe', 'stagiaire']),
        ],
        'testing.authorization.expense.approve' => [
            'path' => '/__test/authorization/expenses/approve',
            'permission' => 'depense.approuver',
            'statuses' => $statuses(['direction']),
        ],
        'testing.authorization.objective.validate' => [
            'path' => '/__test/authorization/objectives/validate',
            'permission' => 'objectif.valider',
            'statuses' => $statuses(['direction']),
        ],
        'testing.authorization.financial-report.validate' => [
            'path' => '/__test/authorization/financial-reports/validate',
            'permission' => 'rapport_financier.valider',
            'statuses' => $statuses(['direction']),
        ],
    ],
];
