<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AlertLevelCard from '../../../Components/AlertLevelCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    month: { type: String, required: true },
    alert: { type: Object, required: true },
    plans: { type: Array, required: true },
    dueLabel: { type: String, required: true },
});

const validation = useForm({});

function validatePlan(id) {
    validation.patch(`/finances/plans-correctifs/${id}/valider`, { preserveScroll: true });
}

// Le plan courant est la version la plus élevée ; les précédentes restent affichées et
// consultables, jamais effacées (AC 17).
const current = () => (props.plans.length ? props.plans[props.plans.length - 1] : null);
</script>

<template>
    <Head title="Plans correctifs" />
    <AppLayout title="Plans correctifs" active-navigation="finance">
        <div class="grid min-w-0 gap-6">
            <header class="grid min-w-0 gap-2">
                <h1 class="break-words text-page-title">Plan correctif</h1>
                <p class="text-ink-secondary">Mois de {{ alert.month_label }}. Le plan reste consultable après le retour au vert.</p>
            </header>

            <AlertLevelCard :alert="alert" />

            <EmptyState
                v-if="plans.length === 0"
                title="Aucun plan correctif n'est enregistré pour ce mois."
                :reason="`Le niveau orange demande l'enregistrement d'un plan correctif sous 48 heures, soit avant le ${dueLabel}.`"
            />

            <p v-if="plans.length === 0 && alert.level === 'orange'" class="rounded-lg border border-warning bg-warning-soft p-3">
                <span aria-hidden="true">▲ </span>Le plan correctif est attendu avant le {{ dueLabel }}. La direction est relancée chaque jour tant qu'il n'existe pas.
            </p>

            <Link
                v-if="plans.length === 0"
                :href="`/finances/plans-correctifs/nouveau?mois=${month}`"
                class="touch-target inline-flex w-fit items-center rounded-lg border-2 border-primary px-4 font-semibold text-primary"
            >
                Enregistrer le plan correctif
            </Link>

            <ol v-if="plans.length" class="grid min-w-0 gap-4">
                <li
                    v-for="plan in plans"
                    :key="plan.id"
                    class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4"
                    :data-testid="`correction-plan-${plan.version}`"
                >
                    <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                        <h2 class="break-words text-section-title">Version {{ plan.version }}</h2>
                        <span class="rounded-full border border-primary px-3 py-1 font-semibold">{{ plan.state_label }}</span>
                    </div>

                    <dl class="grid min-w-0 gap-2">
                        <div><dt class="font-semibold">Constat</dt><dd class="break-words">{{ plan.finding }}</dd></div>
                        <div><dt class="font-semibold">Actions</dt><dd class="break-words">{{ plan.actions }}</dd></div>
                        <div><dt class="font-semibold">Responsables</dt><dd class="break-words">{{ plan.responsibles }}</dd></div>
                        <div><dt class="font-semibold">Échéance</dt><dd>{{ plan.due_on }}</dd></div>
                        <div><dt class="font-semibold">Résultat attendu</dt><dd class="break-words">{{ plan.expected_result }}</dd></div>
                        <div v-if="plan.revision_reason"><dt class="font-semibold">Motif de la révision</dt><dd class="break-words">{{ plan.revision_reason }}</dd></div>
                    </dl>

                    <p class="text-sm text-ink-secondary">
                        Enregistré par {{ plan.created_by }}<span v-if="plan.validated_by"> · validé par {{ plan.validated_by }} le {{ plan.validated_at }}</span>.
                    </p>

                    <AppButton
                        v-if="!plan.is_frozen && plan.id === current()?.id"
                        variant="principal"
                        :busy="validation.processing"
                        @click="validatePlan(plan.id)"
                    >
                        Valider le plan
                    </AppButton>

                    <p v-if="plan.is_frozen" class="text-sm text-ink-secondary">
                        Ce plan est validé : il ne peut plus être modifié. Une révision créera une nouvelle version.
                    </p>
                </li>
            </ol>
        </div>
    </AppLayout>
</template>
