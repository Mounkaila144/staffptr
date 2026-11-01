<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppIcon from '../Components/AppIcon.vue';
import { usePermissions } from '../Composables/usePermissions';

const props = defineProps({
    title: { type: String, required: true },
    backLabel: { type: String, default: '' },
    backHref: { type: String, default: '/' },
    activeNavigation: { type: String, default: 'home' },
    permissions: { type: Array, default: null },
});

const moreOpen = ref(false);
const page = usePage();
const unreadNotificationCount = computed(() => Number(page.props.notifications?.unread_count ?? 0));
const { activeRole, primaryNavigation, moreNavigation } = usePermissions(props.permissions);

function closeMore(event) {
    if (event.key === 'Escape') {
        moreOpen.value = false;
    }
}

// La déconnexion est un POST — la route `logout` n'accepte rien d'autre, et c'est
// volontaire : une adresse qu'un préchargement de navigateur peut visiter ne doit pas
// pouvoir fermer une session. Elle ne peut donc pas être un lien, et se distingue ici.
const LOGOUT_LABEL = 'Déconnexion';

function logout() {
    moreOpen.value = false;
    router.post('/deconnexion');
}

function moreHref(item) {
    if (item === 'Mon rapport du jour') return '/rapports/quotidiens/aujourdhui';
    if (item === 'Mes blocages') return '/blocages';
    if (item === 'Mes demandes' || item === 'Mes demandes de dépense') return '/depenses';
    if (item === 'Objectifs' || item === 'Mes objectifs') return '/objectifs';
    if (item === 'Priorités d’entreprise') return '/priorites-entreprise';
    if (item === 'Tâches' || item === 'Mes tâches') return '/taches';
    if (item === 'Projets') return '/projets';
    if (item === 'Livrables') return '/livrables';
    if (item === 'Absences' || item === 'Mes absences') return '/absences';
    if (item === 'Documents' || item === 'Documents internes') return '/documents-internes';
    // Sans droit de consulter les fiches (super administrateur), « Profil » mène au changement de
    // mot de passe : la fiche personne lui répondrait par un refus.
    if (item === 'Profil' || item === 'Mon profil') {
        const canReadProfile = (page.props.auth?.permissions ?? []).includes('fiche.consulter');
        return canReadProfile && page.props.auth?.person_id ? `/personnes/${page.props.auth.person_id}` : '/mot-de-passe/modifier';
    }
    if (item === 'Organisation') return '/organisation';
    if (item === 'Connexions') return '/connexions';
    if (item === 'Comptes et rôles') return '/comptes';
    if (item === 'Paramètres') return '/parametres';
    if (item === 'Calendrier') return '/calendrier';
    if (item === 'Mes stagiaires' || item === 'Mon stage' || item === 'Stages') return '/stages';
    if (item === "Fiches d'entrée") return '/stages/fiches-entree';
    if (item === 'Arrivées et départs') return '/arrivees-departs';
    if (item === 'Capacité des tuteurs') return '/tuteurs/charge';
    if (item === 'Créneaux de suivi') return '/creneaux-suivi';
    if (item === 'Revues hebdomadaires' || item === 'Ma revue') return '/revues-hebdomadaires';
    if (item === 'Rapports à valider') return '/rapports/validation';
    if (item === 'Blocages de l’équipe') return '/blocages';
    if (item === 'Plans d’accompagnement') return '/plans-accompagnement';
    if (item === "Journal d'audit") return '/journal-audit';
    if (item === 'Réserve') return '/finances/reserve';
    if (item === 'Parts de contribution') return '/finances/parts-de-contribution';
    if (item === 'Rapport mensuel') return '/finances/rapports-mensuels';
    if (item === 'Rapprochement') return '/finances/rapprochements';
    if (item === 'Paiement des dépenses') return '/finances/paiements-depenses';
    if (item === 'Budgets et charges') return '/finances/budgets-mensuels';
    if (item === 'Clients et factures') return '/finances/clients';
    if (item === 'Ma part') return '/finances/parts';
    if (item === 'Tableau de bord direction') return '/tableau-de-bord/direction';
    if (item === 'Plans correctifs') return '/finances/plans-correctifs';
    if (item === 'Recherche') return '/recherche';
    if (item === 'Listes et exports') return '/listes/depenses';
    return '#plus';
}

/*
 * Rangement des entrées secondaires par zone, comme le prévoit le rail desktop du cahier
 * d'interface (§ 3.1). Le contenu de chaque rôle reste décidé par `usePermissions` : cette table
 * ne fait que ranger et illustrer, elle n'ajoute ni ne retire jamais une entrée.
 */
const sections = [
    { key: 'work', label: 'Mon travail' },
    { key: 'money', label: 'Argent' },
    { key: 'steering', label: 'Pilotage' },
    { key: 'people', label: 'Équipe et stages' },
    { key: 'admin', label: 'Administration' },
    { key: 'account', label: 'Mon espace' },
];

const itemMeta = {
    'Mon rapport du jour': ['work', 'report'],
    'Mes objectifs': ['work', 'target'],
    Objectifs: ['work', 'target'],
    'Priorités d’entreprise': ['work', 'target'],
    Tâches: ['work', 'tasks'],
    'Mes tâches': ['work', 'tasks'],
    Projets: ['work', 'project'],
    Livrables: ['work', 'package'],
    'Mes blocages': ['work', 'blocker'],
    Absences: ['work', 'calendar'],
    'Mes absences': ['work', 'calendar'],
    'Mes demandes': ['work', 'expense'],
    'Mes demandes de dépense': ['work', 'expense'],
    'Ma revue': ['work', 'review'],
    'Parts de contribution': ['money', 'pie'],
    'Ma part': ['money', 'pie'],
    Réserve: ['money', 'shield'],
    'Rapport mensuel': ['money', 'chart'],
    Rapprochement: ['money', 'scale'],
    'Paiement des dépenses': ['money', 'expense'],
    'Budgets et charges': ['money', 'coins'],
    'Clients et factures': ['money', 'building'],
    'Plans correctifs': ['money', 'heart'],
    'Tableau de bord direction': ['steering', 'chart'],
    'Revues hebdomadaires': ['steering', 'review'],
    'Rapports à valider': ['people', 'report'],
    'Blocages de l’équipe': ['people', 'blocker'],
    'Plans d’accompagnement': ['people', 'heart'],
    Recherche: ['steering', 'search'],
    'Listes et exports': ['steering', 'export'],
    Stages: ['people', 'intern'],
    'Mes stagiaires': ['people', 'intern'],
    'Mon stage': ['people', 'intern'],
    "Fiches d'entrée": ['people', 'document'],
    'Arrivées et départs': ['people', 'clock'],
    'Capacité des tuteurs': ['people', 'team'],
    'Créneaux de suivi': ['people', 'clock'],
    Organisation: ['people', 'building'],
    'Comptes et rôles': ['admin', 'key'],
    Paramètres: ['admin', 'settings'],
    Calendrier: ['admin', 'calendar'],
    "Journal d'audit": ['admin', 'log'],
    Connexions: ['admin', 'login'],
    'Documents internes': ['account', 'document'],
    Documents: ['account', 'document'],
    'Mon profil': ['account', 'user'],
    Profil: ['account', 'user'],
};

const primaryIcons = {
    home: 'home', approvals: 'approve', team: 'team', finance: 'wallet', report: 'report',
    objectives: 'target', tasks: 'tasks', internship: 'intern', expenses: 'expense',
    contracts: 'contract', accounts: 'key', settings: 'settings', logs: 'log',
};

const roleLabels = {
    super_admin: 'Administration technique', direction: 'Direction', finance: 'Finance',
    tuteur: 'Tutorat', stagiaire: 'Stage', employe: 'Équipe',
};

const currentPath = computed(() => (page.url ?? '/').split(/[?#]/)[0]);

function isCurrent(href) {
    if (!href || href === '/' || href.startsWith('#')) {
        return currentPath.value === href;
    }

    return currentPath.value === href || currentPath.value.startsWith(`${href}/`);
}

// Une entrée sans écran réel (`#plus`) n'est pas affichée : un lien mort n'aide personne.
const groupedNavigation = computed(() => {
    const items = moreNavigation.value
        .filter((label) => label !== LOGOUT_LABEL)
        .map((label) => ({ label, href: moreHref(label), section: itemMeta[label]?.[0] ?? 'account', icon: itemMeta[label]?.[1] ?? 'document' }))
        .filter((item) => !item.href.startsWith('#'));

    return sections
        .map((section) => ({ ...section, items: items.filter((item) => item.section === section.key) }))
        .filter((section) => section.items.length > 0);
});

// Une entrée secondaire qui correspond à la page prend la main : deux entrées allumées à la fois
// ne disent plus où l'on est. L'entrée primaire reste allumée si elle désigne la page même.
const secondaryMatches = computed(() => groupedNavigation.value.some((section) => section.items.some((item) => isCurrent(item.href))));

function isPrimaryActive(item) {
    return item.key === activeNavigation.value && (!secondaryMatches.value || isCurrent(item.href));
}

const activeNavigation = computed(() => props.activeNavigation);

const showsLogout = computed(() => moreNavigation.value.includes(LOGOUT_LABEL));

watch(() => page.url, () => {
    moreOpen.value = false;
});

// Confirmation d'action, affichée quelques secondes. Une page qui reçoit déjà `success` en prop
// l'affiche elle-même : on ne la répète pas.
const toast = ref('');
let toastTimer = null;
watch(() => [page.props.flash?.success, page.props.success], ([flash, own]) => {
    if (!flash || own) {
        return;
    }
    toast.value = flash;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.value = ''; }, 5000);
}, { immediate: true });
</script>

<template>
    <div class="min-h-screen" @keydown="closeMore">
        <a class="skip-link" href="#main-content">Aller au contenu</a>

        <nav class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col bg-rail text-rail-ink md:flex" aria-label="Navigation principale">
            <a href="/" class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
                <span class="grid size-9 place-items-center rounded-lg bg-primary text-sm font-bold tracking-tight text-white" aria-hidden="true">PTR</span>
                <span class="grid leading-tight">
                    <span class="font-semibold text-white">PTR Staff</span>
                    <span class="text-xs text-rail-muted">{{ roleLabels[activeRole] }}</span>
                </span>
            </a>

            <div class="rail-scroll min-h-0 flex-1 overflow-y-auto px-3 py-4">
                <ul class="grid gap-0.5">
                    <li v-for="item in primaryNavigation" :key="item.key">
                        <a
                            :href="item.href"
                            class="flex min-h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors"
                            :class="isPrimaryActive(item) ? 'bg-rail-active text-white' : 'hover:bg-rail-hover hover:text-white'"
                            :aria-current="isPrimaryActive(item) ? 'page' : undefined"
                        >
                            <AppIcon :name="primaryIcons[item.key] ?? 'document'" :size="18" />
                            <span>{{ item.label }}</span>
                            <span v-if="isPrimaryActive(item)" class="ml-auto h-5 w-1 rounded-full bg-white/80" aria-hidden="true" />
                        </a>
                    </li>
                </ul>

                <section v-for="section in groupedNavigation" :key="section.key" class="mt-6" :aria-labelledby="`rail-${section.key}`">
                    <h2 :id="`rail-${section.key}`" class="mb-1.5 px-3 text-[0.6875rem] font-semibold uppercase tracking-wider text-rail-muted">{{ section.label }}</h2>
                    <ul class="grid gap-0.5">
                        <li v-for="item in section.items" :key="item.label">
                            <a
                                :href="item.href"
                                class="flex min-h-9 items-center gap-3 rounded-lg px-3 text-sm transition-colors"
                                :class="isCurrent(item.href) ? 'bg-rail-hover font-medium text-white' : 'hover:bg-rail-hover hover:text-white'"
                                :aria-current="isCurrent(item.href) ? 'page' : undefined"
                            >
                                <AppIcon :name="item.icon" :size="17" />
                                <span class="min-w-0 truncate">{{ item.label }}</span>
                            </a>
                        </li>
                    </ul>
                </section>
            </div>

            <div v-if="showsLogout" class="shrink-0 border-t border-white/10 p-3">
                <button type="button" class="flex min-h-10 w-full items-center gap-3 rounded-lg px-3 text-sm transition-colors hover:bg-rail-hover hover:text-white" @click="logout">
                    <AppIcon name="logout" :size="18" />
                    <span>{{ LOGOUT_LABEL }}</span>
                </button>
            </div>
        </nav>

        <div class="md:pl-64">
            <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-separator bg-surface/90 px-4 backdrop-blur md:px-8" aria-label="En-tête">
                <a href="/" class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary text-xs font-bold text-white md:hidden" aria-label="PTR Staff, accueil">PTR</a>
                <a v-if="backLabel" :href="backHref" class="touch-target inline-flex min-w-0 max-w-[45%] items-center gap-1 rounded-lg pr-2 text-sm font-semibold text-primary hover:text-primary-strong">
                    <AppIcon name="back" :size="18" class="shrink-0" /><span class="truncate">{{ backLabel }}</span>
                </a>
                <span v-if="backLabel" class="hidden h-5 w-px bg-separator sm:block" aria-hidden="true" />
                <p class="min-w-0 flex-1 truncate font-semibold text-ink">{{ title }}</p>
                <a
                    href="/notifications"
                    class="touch-target relative inline-flex shrink-0 items-center justify-center rounded-full text-ink-secondary transition-colors hover:bg-neutral-soft hover:text-ink"
                    :aria-label="unreadNotificationCount > 0 ? `Notifications, ${unreadNotificationCount} non lue${unreadNotificationCount > 1 ? 's' : ''}` : 'Notifications, aucune non lue'"
                >
                    <AppIcon name="bell" :size="20" />
                    <span v-if="unreadNotificationCount > 0" class="absolute right-1 top-1 min-w-5 rounded-full bg-danger px-1 text-center text-[0.6875rem] font-bold leading-5 text-white ring-2 ring-surface" aria-hidden="true">
                        {{ unreadNotificationCount > 99 ? '99+' : unreadNotificationCount }}
                    </span>
                </a>
            </header>

            <main id="main-content" tabindex="-1" class="mx-auto min-h-[calc(100vh-4rem)] max-w-content px-4 pb-28 pt-6 outline-none md:px-8 md:pb-12 md:pt-8">
                <slot />
            </main>
        </div>

        <div class="pointer-events-none fixed inset-x-0 bottom-20 z-50 flex justify-center px-4 md:bottom-6 md:pl-64" aria-live="polite">
            <p v-if="toast" class="pointer-events-auto flex max-w-lg items-center gap-3 rounded-xl border border-success/30 bg-surface px-4 py-3 text-sm font-medium text-ink shadow-raised" role="status">
                <span class="grid size-7 shrink-0 place-items-center rounded-full bg-success-soft text-success"><AppIcon name="approve" :size="16" /></span>
                <span class="min-w-0">{{ toast }}</span>
                <button type="button" class="touch-target -my-2 -mr-2 inline-flex items-center justify-center rounded-lg text-ink-muted hover:text-ink" aria-label="Fermer le message" @click="toast = ''"><AppIcon name="close" :size="16" /></button>
            </p>
        </div>

        <nav class="fixed inset-x-0 bottom-0 z-40 h-16 border-t border-separator bg-surface md:hidden" aria-label="Navigation principale sur téléphone">
            <ul class="grid h-full grid-cols-5 gap-2 px-1">
                <li v-for="item in primaryNavigation.slice(0, 4)" :key="item.key">
                    <a
                        :href="item.href"
                        class="touch-target relative flex h-full flex-col items-center justify-center gap-1 text-[0.6875rem] font-medium"
                        :class="item.key === activeNavigation ? 'text-primary' : 'text-ink-secondary'"
                        :aria-current="item.key === activeNavigation ? 'page' : undefined"
                    >
                        <span v-if="item.key === activeNavigation" class="absolute inset-x-4 top-0 h-0.5 rounded-full bg-primary" aria-hidden="true" />
                        <AppIcon :name="primaryIcons[item.key] ?? 'document'" :size="22" />
                        <span class="max-w-full truncate">{{ item.label }}</span>
                    </a>
                </li>
                <li>
                    <button
                        type="button"
                        class="touch-target flex h-full w-full flex-col items-center justify-center gap-1 text-[0.6875rem] font-medium"
                        :class="moreOpen ? 'text-primary' : 'text-ink-secondary'"
                        :aria-expanded="moreOpen"
                        aria-controls="mobile-more-navigation"
                        @click="moreOpen = !moreOpen"
                    >
                        <AppIcon :name="moreOpen ? 'close' : 'more'" :size="22" />
                        <span>Plus</span>
                    </button>
                </li>
            </ul>
        </nav>

        <div v-if="moreOpen" class="fixed inset-0 z-30 bg-ink/40 md:hidden" aria-hidden="true" @click="moreOpen = false" />
        <div
            v-if="moreOpen"
            id="mobile-more-navigation"
            class="fixed inset-x-0 bottom-16 z-40 max-h-[75vh] overflow-y-auto rounded-t-2xl border-t border-separator bg-surface px-4 pb-4 pt-3 shadow-raised md:hidden"
        >
            <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-separator-strong" aria-hidden="true" />
            <section v-for="section in groupedNavigation" :key="section.key" class="mb-4" :aria-labelledby="`sheet-${section.key}`">
                <h2 :id="`sheet-${section.key}`" class="mb-1 px-2 text-xs font-semibold uppercase tracking-wider text-ink-muted">{{ section.label }}</h2>
                <ul class="grid gap-0.5">
                    <li v-for="item in section.items" :key="item.label">
                        <a :href="item.href" class="touch-target flex items-center gap-3 rounded-lg px-2 text-ink" :class="isCurrent(item.href) ? 'bg-primary-soft font-semibold text-primary' : 'hover:bg-neutral-soft'">
                            <AppIcon :name="item.icon" :size="20" class="text-ink-secondary" />
                            <span>{{ item.label }}</span>
                        </a>
                    </li>
                </ul>
            </section>
            <button v-if="showsLogout" type="button" class="touch-target flex w-full items-center gap-3 rounded-lg border-t border-separator px-2 pt-2 text-left text-danger" @click="logout">
                <AppIcon name="logout" :size="20" />
                <span>{{ LOGOUT_LABEL }}</span>
            </button>
        </div>
    </div>
</template>
