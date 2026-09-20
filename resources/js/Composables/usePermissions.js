import { computed, unref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const rolePriority = ['super_admin', 'direction', 'finance', 'tuteur', 'stagiaire', 'employe'];

const navigationByRole = {
    employe: {
        primary: [
            ['Accueil', 'home', '⌂', null],
            ['Rapport', 'report', '▤', 'rapport_quotidien.consulter'],
            ['Objectifs', 'objectives', '◎', 'objectif_individuel.consulter'],
            ['Tâches', 'tasks', '✓', 'projet.consulter'],
        ],
        more: ['Recherche', 'Projets', 'Livrables', 'Mes blocages', 'Mes absences', 'Mes demandes de dépense', 'Ma revue', 'Ma part', 'Documents internes', 'Mon profil', 'Déconnexion'],
    },
    stagiaire: {
        primary: [
            ['Accueil', 'home', '⌂', null],
            ['Rapport', 'report', '▤', 'rapport_quotidien.consulter'],
            ['Mon stage', 'internship', '◇', 'stagiaire.consulter'],
            ['Tâches', 'tasks', '✓', 'projet.consulter'],
        ],
        more: ['Recherche', 'Objectifs', 'Projets', 'Livrables', 'Mes blocages', 'Mes absences', 'Mes demandes', 'Ma revue', 'Documents internes', 'Mon profil', 'Déconnexion'],
    },
    tuteur: {
        primary: [
            ['Accueil', 'home', '⌂', null],
            ['Équipe', 'team', '♙', 'stagiaire.consulter'],
            ['Rapport', 'report', '▤', 'rapport_quotidien.consulter'],
            ['Objectifs', 'objectives', '◎', 'objectif_individuel.consulter'],
        ],
        more: ['Tâches', 'Projets', 'Livrables', 'Mes stagiaires', 'Créneaux de suivi', 'Revues hebdomadaires', 'Mes blocages', 'Mes absences', 'Mes demandes', 'Recherche', 'Documents', 'Profil', 'Déconnexion'],
    },
    direction: {
        primary: [
            ['Accueil', 'home', '⌂', null],
            ['À approuver', 'approvals', '✓', 'depense.approuver'],
            ['Équipe', 'team', '♙', 'compte.consulter'],
            ['Argent', 'finance', '¤', 'finance.ecriture.consulter'],
        ],
        // La direction détient `stagiaire.consulter` et `stagiaire.gerer`, mais aucune entrée ne
        // menait au parcours de stage : un compte de stagiaire restait « invité » sans que rien
        // n'indique où remplir la fiche d'entrée, désigner le tuteur, puis activer le compte.
        more: ['Tableau de bord direction', 'Mon rapport du jour', 'Mes objectifs', 'Tâches', 'Projets', 'Livrables', 'Absences', "Fiches d'entrée", 'Stages', 'Capacité des tuteurs', 'Organisation', 'Comptes et rôles', 'Paramètres', 'Calendrier', "Journal d'audit", 'Connexions', 'Réserve', 'Rapport mensuel', 'Plans correctifs', 'Recherche', 'Listes et exports', 'Documents', 'Profil', 'Déconnexion'],
    },
    finance: {
        primary: [
            ['Accueil', 'home', '⌂', null],
            ['Argent', 'finance', '¤', 'finance.ecriture.consulter'],
            ['Dépenses', 'expenses', '▥', 'depense.consulter'],
            ['Contrats', 'contracts', '▧', 'client.consulter'],
        ],
        more: ['Tableau de bord direction', 'Rapprochement', 'Rapport mensuel', 'Plans correctifs', 'Budgets et charges', 'Clients et factures', 'Mon rapport du jour', 'Mes objectifs', 'Tâches', 'Projets', 'Livrables', 'Mes absences', 'Recherche', 'Listes et exports', 'Documents', 'Profil', 'Déconnexion'],
    },
    super_admin: {
        primary: [
            ['Accueil', 'home', '⌂', null],
            ['Comptes', 'accounts', '♙', 'compte.technique.gerer'],
            ['Paramètres', 'settings', '⚙', 'parametre.gerer'],
            ['Journaux', 'logs', '▤', 'journal_technique.consulter'],
        ],
        more: ["Santé de l'application", 'Profil', 'Déconnexion'],
    },
};

// La clé `team` mène à deux écrans différents selon le rôle : le tuteur y voit ses stagiaires,
// la direction y gère les comptes. Une table par clé ne suffit donc pas, d'où cette surcharge.
const navigationHrefOverridesByRole = {
    tuteur: { team: '/stages' },
    direction: { team: '/comptes' },
};

function normalizeNavigationItem([label, key, glyph, permission], role) {
    return {
        label,
        key,
        glyph,
        permission,
        href: key === 'home' ? '/' : (navigationHrefOverridesByRole[role]?.[key] ?? {
            accounts: '/comptes',
            approvals: '/depenses/approbations',
            expenses: '/depenses',
            internship: '/stages',
            objectives: '/objectifs',
            report: '/rapports',
            tasks: '/taches/aujourdhui',
            settings: '/parametres',
            finance: '/finances/tableau-de-bord',
            contracts: '/finances/contrats',
        }[key] ?? `/#${key}`),
    };
}

export function createPermissionChecker(permissions = []) {
    const values = new Set(permissions);

    return {
        can: (permission) => values.has(permission),
        canAny: (candidates) => candidates.some((permission) => values.has(permission)),
    };
}

export function usePermissions(permissionSource = null) {
    const page = permissionSource === null ? usePage() : null;
    const permissions = computed(() => {
        if (permissionSource !== null) {
            return unref(permissionSource) ?? [];
        }

        return page?.props.auth?.permissions ?? [];
    });
    const checker = computed(() => createPermissionChecker(permissions.value));
    const roles = computed(() => rolePriority.filter((role) => checker.value.can(`role:${role}`)));
    const activeRole = computed(() => roles.value[0] ?? 'employe');
    const primaryNavigation = computed(() => navigationByRole[activeRole.value].primary
        .map((item) => normalizeNavigationItem(item, activeRole.value))
        .filter((item) => item.permission === null || checker.value.can(item.permission)));
    const moreNavigation = computed(() => {
        const entries = roles.value.flatMap((role) => navigationByRole[role].more);

        return [...new Set(entries.length > 0 ? entries : navigationByRole.employe.more)];
    });

    return {
        // This composable only hides interface elements. Server authorization remains mandatory.
        can: (permission) => checker.value.can(permission),
        canAny: (candidates) => checker.value.canAny(candidates),
        activeRole,
        moreNavigation,
        permissions,
        primaryNavigation,
    };
}
