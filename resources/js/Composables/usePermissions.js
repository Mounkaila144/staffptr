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
        more: ['Mes blocages', 'Mes absences', 'Mes demandes de dépense', 'Ma revue', 'Ma part', 'Documents internes', 'Mon profil', 'Déconnexion'],
    },
    stagiaire: {
        primary: [
            ['Accueil', 'home', '⌂', null],
            ['Rapport', 'report', '▤', 'rapport_quotidien.consulter'],
            ['Mon stage', 'internship', '◇', 'stagiaire.consulter'],
            ['Tâches', 'tasks', '✓', 'projet.consulter'],
        ],
        more: ['Mes blocages', 'Mes absences', 'Mes demandes', 'Ma revue', 'Documents internes', 'Mon profil', 'Déconnexion'],
    },
    tuteur: {
        primary: [
            ['Accueil', 'home', '⌂', null],
            ['Équipe', 'team', '♙', 'stagiaire.consulter'],
            ['Rapport', 'report', '▤', 'rapport_quotidien.consulter'],
            ['Objectifs', 'objectives', '◎', 'objectif_individuel.consulter'],
        ],
        more: ['Mes stagiaires', 'Créneaux de suivi', 'Revues hebdomadaires', 'Mes blocages', 'Mes absences', 'Mes demandes', 'Documents', 'Profil', 'Déconnexion'],
    },
    direction: {
        primary: [
            ['Accueil', 'home', '⌂', null],
            ['À approuver', 'approvals', '✓', 'depense.approuver'],
            ['Équipe', 'team', '♙', 'compte.consulter'],
            ['Argent', 'finance', '¤', 'finance.ecriture.consulter'],
        ],
        more: ['Mon rapport du jour', 'Mes objectifs', 'Comptes et rôles', 'Paramètres', 'Calendrier', "Journal d'audit", 'Connexions', 'Réserve', 'Rapport mensuel', 'Recherche', 'Documents', 'Profil', 'Déconnexion'],
    },
    finance: {
        primary: [
            ['Accueil', 'home', '⌂', null],
            ['Argent', 'finance', '¤', 'finance.ecriture.consulter'],
            ['Dépenses', 'expenses', '▥', 'depense.consulter'],
            ['Contrats', 'contracts', '▧', 'client.consulter'],
        ],
        more: ['Rapprochement', 'Rapport mensuel', 'Budgets et charges', 'Clients et factures', 'Mon rapport du jour', 'Mes objectifs', 'Recherche', 'Documents', 'Profil', 'Déconnexion'],
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

function normalizeNavigationItem([label, key, glyph, permission]) {
    return {
        label,
        key,
        glyph,
        permission,
        href: key === 'home' ? '/' : (key === 'accounts' ? '/comptes' : `/#${key}`),
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
        .map(normalizeNavigationItem)
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
