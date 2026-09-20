<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import AppButton from '../../../../Components/AppButton.vue';

const props = defineProps({
    form: { type: Object, required: true },
    readiness: { type: Object, required: true },
    permissions: { type: Object, required: true },
    success: { type: String, default: null },
});

const submitForm = useForm({});
const decisionForm = useForm({ approved: true, decision_reason: '' });
const activationForm = useForm({});

// L'activation ne transporte aucune donnée : les conditions sont relues de l'état du système,
// jamais envoyées par le formulaire. L'écran ne fait qu'ouvrir la porte quand elles sont réunies.
function activateIntern() {
    activationForm.post(`/stages/stagiaires/${props.form.candidate_id}/activer`, { preserveScroll: true });
}

function submitIntake() {
    submitForm.patch(`/stages/fiches-entree/${props.form.id}/soumettre`, { preserveScroll: true });
}

function decide(approved) {
    decisionForm.approved = approved;
    decisionForm.patch(`/stages/fiches-entree/${props.form.id}/decision`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Fiche d’entrée de ${form.candidate}`" />
    <AppLayout :title="`Fiche d’entrée de ${form.candidate}`" active-navigation="internship">
        <div class="grid min-w-0 gap-5">
            <header class="grid gap-1">
                <h1 class="text-screen-title">Fiche d’entrée de {{ form.candidate }}</h1>
                <p class="text-ink-secondary">{{ form.state_label }} — {{ form.duration_weeks }} semaines.</p>
                <p class="text-ink-secondary">Responsable : {{ form.manager }}. Tuteur : {{ form.tutor || 'à désigner' }}.</p>
            </header>

            <p v-if="success" class="rounded-lg border border-success bg-success-soft p-3 text-success" role="status">
                {{ success }}
            </p>

            <section class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Le stage</h2>
                <div class="grid gap-1">
                    <h3 class="font-semibold">Besoin réel</h3>
                    <p class="text-ink-secondary">{{ form.real_need }}</p>
                </div>
                <div class="grid gap-1">
                    <h3 class="font-semibold">Mission</h3>
                    <p class="text-ink-secondary">{{ form.mission }}</p>
                </div>
                <div class="grid gap-1">
                    <h3 class="font-semibold">Outils</h3>
                    <p class="text-ink-secondary">{{ form.tools }}</p>
                </div>
            </section>

            <section class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Résultats attendus</h2>
                <ol class="grid list-inside list-decimal gap-1">
                    <li v-for="outcome in form.outcomes" :key="outcome.id" class="text-ink-secondary">
                        {{ outcome.description }}
                    </li>
                </ol>
            </section>

            <!-- Les trois conditions d'activation sont énoncées séparément, chacune avec son
                 état écrit en toutes lettres : l'information n'est jamais portée par la couleur. -->
            <section class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Conditions d’activation</h2>
                <ul class="grid gap-1">
                    <li class="text-ink-secondary">
                        Fiche d’entrée approuvée : {{ readiness.approved_intake_form ? 'oui' : 'non' }}
                    </li>
                    <li class="text-ink-secondary">
                        Tuteur désigné : {{ readiness.designated_tutor ? 'oui' : 'non' }}
                    </li>
                    <li class="text-ink-secondary">
                        Trois objectifs enregistrés : {{ readiness.objective_count }} sur 3
                    </li>
                </ul>
                <p v-if="!readiness.satisfied" class="text-ink-secondary">
                    Activation impossible pour l’instant : {{ readiness.missing.join(', ') }}.
                </p>
                <template v-else>
                    <p class="text-ink-secondary">Les conditions sont réunies.</p>
                    <form v-if="permissions.activate" @submit.prevent="activateIntern">
                        <AppButton type="submit" :disabled="activationForm.processing">
                            Activer le compte du stagiaire
                        </AppButton>
                    </form>
                </template>
            </section>

            <section v-if="form.decided_at" class="grid min-w-0 gap-1 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Décision</h2>
                <p class="text-ink-secondary">{{ form.state_label }} par {{ form.decided_by }} le {{ form.decided_at }}.</p>
                <p v-if="form.decision_reason" class="text-ink-secondary">{{ form.decision_reason }}</p>
            </section>

            <form v-if="permissions.submit" @submit.prevent="submitIntake">
                <AppButton type="submit" :disabled="submitForm.processing">Soumettre à la direction</AppButton>
            </form>

            <section v-if="permissions.decide" class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Décider</h2>
                <label class="grid gap-1 font-semibold" for="decision-reason">Motif, obligatoire en cas de refus
                    <textarea
                        id="decision-reason"
                        v-model="decisionForm.decision_reason"
                        rows="3"
                        class="rounded-lg border border-separator p-3"
                        :aria-describedby="decisionForm.errors.decision_reason ? 'decision-reason-error' : undefined"
                    />
                </label>
                <p v-if="decisionForm.errors.decision_reason" id="decision-reason-error" class="text-danger" role="alert">
                    {{ decisionForm.errors.decision_reason }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <AppButton type="button" :disabled="decisionForm.processing" @click="decide(true)">Approuver</AppButton>
                    <AppButton type="button" variant="secondaire" :disabled="decisionForm.processing" @click="decide(false)">Refuser</AppButton>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
