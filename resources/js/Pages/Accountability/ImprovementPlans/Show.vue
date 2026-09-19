<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AppButton from '../../../Components/AppButton.vue';

const props = defineProps({
    plan: { type: Object, required: true },
    permissions: { type: Object, required: true },
    success: { type: String, default: null },
});

const closeForm = useForm({ observed_result: '' });

function closePlan() {
    closeForm.patch(`/plans-accompagnement/${props.plan.id}/terminer`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Accompagnement de ${plan.subject}`" />
    <AppLayout :title="`Accompagnement de ${plan.subject}`" active-navigation="improvement-plans">
        <div class="grid min-w-0 gap-5">
            <header class="grid gap-1">
                <h1 class="text-screen-title">Accompagnement de {{ plan.subject }}</h1>
                <p class="text-ink-secondary">
                    Du {{ plan.start_date }} au {{ plan.end_date }}, soit {{ plan.duration_days }} jours.
                </p>
                <p class="text-ink-secondary">Mis en place par {{ plan.created_by }} — {{ plan.state_label }}.</p>
            </header>

            <p v-if="success" class="rounded-lg border border-success bg-success-soft p-3 text-success" role="status">
                {{ success }}
            </p>

            <section class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Aide fournie</h2>
                <p class="text-ink-secondary">{{ plan.support_provided }}</p>
            </section>

            <section class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Actions convenues</h2>
                <ul class="grid gap-2">
                    <li v-for="action in plan.actions" :key="action.id" class="grid gap-0.5 border-t border-separator pt-2 first:border-t-0 first:pt-0">
                        <span class="font-semibold">{{ action.description }}</span>
                        <span class="text-ink-secondary">
                            Prévue le {{ action.due_date }} — {{ action.completed ? 'réalisée' : 'à faire' }}
                        </span>
                    </li>
                </ul>
            </section>

            <section v-if="plan.observed_result" class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Résultat constaté</h2>
                <p class="text-ink-secondary">{{ plan.observed_result }}</p>
                <p v-if="plan.closed_at" class="text-ink-secondary">Accompagnement terminé le {{ plan.closed_at }}.</p>
            </section>

            <form
                v-if="permissions.close"
                class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4"
                @submit.prevent="closePlan"
            >
                <h2 class="text-section-title">Terminer l’accompagnement</h2>
                <label class="grid gap-1 font-semibold" for="observed-result">Résultat constaté
                    <textarea
                        id="observed-result"
                        v-model="closeForm.observed_result"
                        rows="4"
                        required
                        class="rounded-lg border border-separator p-3"
                        :aria-describedby="closeForm.errors.observed_result ? 'observed-result-error' : undefined"
                    />
                </label>
                <p v-if="closeForm.errors.observed_result" id="observed-result-error" class="text-danger" role="alert">
                    {{ closeForm.errors.observed_result }}
                </p>
                <AppButton type="submit" :disabled="closeForm.processing">Terminer l’accompagnement</AppButton>
            </form>
        </div>
    </AppLayout>
</template>
