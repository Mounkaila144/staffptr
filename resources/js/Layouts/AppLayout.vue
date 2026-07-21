<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
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
const { primaryNavigation, moreNavigation } = usePermissions(props.permissions);

function closeMore(event) {
    if (event.key === 'Escape') {
        moreOpen.value = false;
    }
}

function moreHref(item) {
    if (item === 'Profil' || item === 'Mon profil') return page.props.auth?.person_id ? `/personnes/${page.props.auth.person_id}` : '/';
    if (item === 'Organisation') return '/organisation';
    if (item === 'Connexions') return '/connexions';
    if (item === 'Comptes et rôles') return '/comptes';
    if (item === 'Paramètres') return '/parametres';
    if (item === "Journal d'audit") return '/journal-audit';
    return '#plus';
}
</script>

<template>
    <div @keydown="closeMore">
        <a class="skip-link" href="#main-content">Aller au contenu</a>
        <header class="fixed inset-x-0 top-0 z-30 flex h-14 items-center gap-3 border-b border-separator bg-surface px-4 md:pl-56" aria-label="En-tête">
            <a v-if="backLabel" :href="backHref" class="touch-target inline-flex items-center gap-2 font-semibold text-primary">
                <span aria-hidden="true">←</span>{{ backLabel }}
            </a>
            <p class="min-w-0 flex-1 truncate font-semibold">{{ title }}</p>
            <a
                href="/notifications"
                class="touch-target relative inline-flex items-center justify-center rounded-lg text-xl text-primary"
                :aria-label="unreadNotificationCount > 0 ? `Notifications, ${unreadNotificationCount} non lue${unreadNotificationCount > 1 ? 's' : ''}` : 'Notifications, aucune non lue'"
            >
                <span aria-hidden="true">♢</span>
                <span v-if="unreadNotificationCount > 0" class="absolute right-0 top-0 min-w-5 rounded-full bg-danger px-1 text-center text-xs font-bold leading-5 text-white" aria-hidden="true">
                    {{ unreadNotificationCount > 99 ? '99+' : unreadNotificationCount }}
                </span>
            </a>
        </header>

        <nav class="fixed inset-y-0 left-0 z-40 hidden w-52 border-r border-separator bg-surface px-3 py-4 md:block" aria-label="Navigation principale">
            <a href="/" class="touch-target mb-5 flex items-center px-3 font-bold text-primary">PTR Staff</a>
            <ul class="grid gap-2">
                <li v-for="item in primaryNavigation" :key="item.key">
                    <a :href="item.href" class="touch-target flex items-center gap-3 rounded-lg px-3" :class="item.key === activeNavigation ? 'border-l-4 border-primary bg-primary-soft font-semibold text-primary' : 'text-ink-secondary'" :aria-current="item.key === activeNavigation ? 'page' : undefined">
                        <span aria-hidden="true">{{ item.glyph }}</span><span>{{ item.label }}</span>
                    </a>
                </li>
                <li>
                    <button type="button" class="touch-target flex w-full items-center gap-3 rounded-lg px-3 text-ink-secondary" :aria-expanded="moreOpen" aria-controls="more-navigation" @click="moreOpen = !moreOpen">
                        <span aria-hidden="true">•••</span><span>Plus</span>
                    </button>
                </li>
            </ul>
            <ul v-if="moreOpen" id="more-navigation" class="mt-2 max-h-[45vh] overflow-y-auto border-t border-separator pt-2">
                <li v-for="item in moreNavigation" :key="item"><a :href="moreHref(item)" class="touch-target flex items-center rounded-lg px-3 text-sm text-ink-secondary">{{ item }}</a></li>
            </ul>
        </nav>

        <main id="main-content" tabindex="-1" class="mx-auto min-h-screen max-w-content px-4 pb-20 pt-20 md:ml-52 md:pb-8">
            <slot />
        </main>

        <nav class="fixed inset-x-0 bottom-0 z-40 h-14 border-t border-separator bg-surface md:hidden" aria-label="Navigation principale sur téléphone">
            <ul class="grid h-full grid-cols-5 gap-2 px-1">
                <li v-for="item in primaryNavigation.slice(0, 4)" :key="item.key">
                    <a :href="item.href" class="touch-target flex h-full flex-col items-center justify-center gap-0.5 text-xs" :class="item.key === activeNavigation ? 'border-t-4 border-primary bg-primary-soft font-semibold text-primary' : 'text-ink-secondary'" :aria-current="item.key === activeNavigation ? 'page' : undefined">
                        <span aria-hidden="true">{{ item.glyph }}</span><span>{{ item.label }}</span>
                    </a>
                </li>
                <li>
                    <button type="button" class="touch-target flex h-full w-full flex-col items-center justify-center gap-0.5 text-xs text-ink-secondary" :aria-expanded="moreOpen" aria-controls="mobile-more-navigation" @click="moreOpen = !moreOpen">
                        <span aria-hidden="true">•••</span><span>Plus</span>
                    </button>
                </li>
            </ul>
            <div v-if="moreOpen" id="mobile-more-navigation" class="absolute inset-x-0 bottom-14 max-h-[60vh] overflow-y-auto border-t border-separator bg-surface p-3 shadow-xl">
                <p class="mb-2 font-semibold">Plus</p>
                <ul class="grid gap-2"><li v-for="item in moreNavigation" :key="item"><a :href="moreHref(item)" class="touch-target flex items-center rounded-lg px-3 text-ink-secondary">{{ item }}</a></li></ul>
            </div>
        </nav>
    </div>
</template>
