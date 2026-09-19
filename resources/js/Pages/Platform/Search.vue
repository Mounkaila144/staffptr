<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import EmptyState from '../../Components/EmptyState.vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    results: { type: Object, required: true },
    statusOptions: { type: Object, required: true },
});

const term = ref(props.results.term);
const type = ref(props.results.type ?? '');
const from = ref(props.results.from ?? '');
const to = ref(props.results.to ?? '');
const statut = ref(props.results.status ?? '');

let debounce = null;

// La recherche part au fil de la frappe, mais pas à chaque touche : sur 3G dégradée, une requête
// par caractère saturerait le lien avant d'afficher quoi que ce soit.
function run() {
    window.clearTimeout(debounce);
    debounce = window.setTimeout(() => {
        router.get(
            '/recherche',
            { q: term.value, type: type.value, from: from.value, to: to.value, statut: statut.value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 350);
}

watch([term, type, from, to, statut], run);

function reset() {
    type.value = '';
    from.value = '';
    to.value = '';
    statut.value = '';
}
</script>

<template>
    <Head title="Recherche" />
    <AppLayout title="Recherche" active-navigation="home">
        <div class="grid min-w-0 gap-6">
            <header class="grid min-w-0 gap-2">
                <h1 class="break-words text-page-title">Recherche</h1>
                <p class="text-ink-secondary">Personnes, projets et objectifs de votre périmètre.</p>
            </header>

            <form class="grid min-w-0 gap-3" role="search" @submit.prevent="run">
                <div class="grid min-w-0 gap-2">
                    <label for="search-term" class="text-field-label font-semibold">Rechercher</label>
                    <input
                        id="search-term"
                        v-model="term"
                        name="q"
                        type="search"
                        autocomplete="off"
                        placeholder="Nom, projet, objectif…"
                        class="min-h-12 w-full min-w-0 rounded-lg border border-separator bg-surface px-3 text-base"
                        aria-describedby="search-term-hint"
                    />
                    <p id="search-term-hint" class="text-sm text-ink-secondary">Deux caractères au minimum.</p>
                </div>

                <!-- Filtres empilés sur téléphone, en ligne dès `sm` : rien ne déborde à 320 px. -->
                <div class="grid min-w-0 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="grid min-w-0 gap-1">
                        <label for="search-type" class="text-field-label font-semibold">Type</label>
                        <select id="search-type" v-model="type" class="min-h-12 w-full min-w-0 rounded-lg border border-separator bg-surface px-3">
                            <option value="">Tous les types</option>
                            <option value="person">Personnes</option>
                            <option value="project">Projets</option>
                            <option value="objective">Objectifs</option>
                        </select>
                    </div>
                    <div class="grid min-w-0 gap-1">
                        <label for="search-from" class="text-field-label font-semibold">À partir du</label>
                        <input id="search-from" v-model="from" type="date" class="min-h-12 w-full min-w-0 rounded-lg border border-separator bg-surface px-3" />
                    </div>
                    <div class="grid min-w-0 gap-1">
                        <label for="search-to" class="text-field-label font-semibold">Jusqu'au</label>
                        <input id="search-to" v-model="to" type="date" class="min-h-12 w-full min-w-0 rounded-lg border border-separator bg-surface px-3" />
                    </div>
                    <div class="grid min-w-0 gap-1">
                        <label for="search-status" class="text-field-label font-semibold">Statut</label>
                        <select id="search-status" v-model="statut" class="min-h-12 w-full min-w-0 rounded-lg border border-separator bg-surface px-3">
                            <option value="">Tous les statuts</option>
                            <optgroup v-if="!type || type === 'person'" label="Personnes">
                                <option v-for="option in statusOptions.person" :key="`p-${option.value}`" :value="option.value">{{ option.label }}</option>
                            </optgroup>
                            <optgroup v-if="!type || type === 'project'" label="Projets">
                                <option v-for="option in statusOptions.project" :key="`pr-${option.value}`" :value="option.value">{{ option.label }}</option>
                            </optgroup>
                            <optgroup v-if="!type || type === 'objective'" label="Objectifs">
                                <option v-for="option in statusOptions.objective" :key="`o-${option.value}`" :value="option.value">{{ option.label }}</option>
                            </optgroup>
                        </select>
                    </div>
                </div>

                <button
                    v-if="type || from || to || statut"
                    type="button"
                    class="touch-target inline-flex w-fit items-center rounded-lg border border-primary px-4 font-semibold text-primary"
                    @click="reset"
                >
                    Réinitialiser les filtres
                </button>
            </form>

            <!--
                AC 5 : l'état initial et le vide de recherche portent des messages différents et
                appellent des gestes différents.
            -->
            <EmptyState
                v-if="!results.has_query"
                title="Que cherchez-vous ?"
                :reason="results.empty_message"
            />

            <EmptyState
                v-else-if="results.total === 0"
                title="Aucun résultat"
                :reason="results.empty_message"
            />

            <template v-else>
                <p class="font-semibold" role="status" data-testid="search-total">
                    {{ results.total }} résultat{{ results.total > 1 ? 's' : '' }}
                </p>

                <section
                    v-for="group in results.groups"
                    :key="group.key"
                    class="grid min-w-0 gap-3"
                    :aria-labelledby="`group-${group.key}`"
                    :data-testid="`search-group-${group.key}`"
                >
                    <h2 :id="`group-${group.key}`" class="text-section-title">
                        {{ group.label }} <span class="font-normal text-ink-secondary">({{ group.count }})</span>
                    </h2>

                    <ul v-if="group.items.length" class="grid min-w-0 gap-2">
                        <li v-for="item in group.items" :key="`${group.key}-${item.id}`" class="grid min-w-0 rounded-lg border border-separator bg-surface p-3">
                            <Link :href="item.url" class="touch-target inline-flex min-w-0 items-center break-words font-semibold text-primary underline-offset-4 hover:underline">
                                {{ item.title }}
                            </Link>
                            <span class="break-words text-sm text-ink-secondary">{{ item.excerpt }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-ink-secondary">Aucun résultat dans cette catégorie.</p>

                    <p v-if="group.truncated" class="text-sm text-ink-secondary">
                        Seuls les {{ group.items.length }} résultats les plus récents sont affichés. Affinez votre recherche pour en voir d'autres.
                    </p>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
