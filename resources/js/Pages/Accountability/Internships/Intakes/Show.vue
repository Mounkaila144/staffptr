<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import AppButton from '../../../../Components/AppButton.vue';
import AppModal from '../../../../Components/AppModal.vue';
import PageHeader from '../../../../Components/PageHeader.vue';

const props = defineProps({
    form: { type: Object, required: true },
    readiness: { type: Object, required: true },
    already_activated: { type: Boolean, default: false },
    tutor_at_limit: { type: String, default: null },
    objectives: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },
    default_due_date: { type: String, default: '' },
    permissions: { type: Object, required: true },
});

const submitForm = useForm({});
const decisionForm = useForm({ approved: true, decision_reason: '' });
const activationForm = useForm({});
const refusing = ref(false);
const addingObjective = ref(false);

const blankObjective = () => ({
    title: '',
    description: '',
    indicator: '',
    target_value: '',
    expected_evidence: '',
    due_date: props.default_due_date,
    priority: 'normale',
});
const objectiveForm = useForm(blankObjective());

// Toute la mise en route tient sur cet écran, en trois étapes : la fiche, les objectifs,
// l'activation. Chaque étape dit en toutes lettres si elle est faite.
const formDone = computed(() => props.readiness.approved_intake_form);
const objectivesDone = computed(() => props.readiness.objective_count >= 3);
const missingObjectives = computed(() => Math.max(0, 3 - props.readiness.objective_count));

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
    decisionForm.patch(`/stages/fiches-entree/${props.form.id}/decision`, {
        preserveScroll: true,
        onSuccess: () => { refusing.value = false; },
    });
}

function openObjective() {
    objectiveForm.defaults(blankObjective());
    objectiveForm.reset();
    objectiveForm.clearErrors();
    addingObjective.value = true;
}

// « Enregistrer et en ajouter un autre » garde la fenêtre ouverte : trois objectifs d'affilée,
// sans rouvrir quoi que ce soit.
function saveObjective(another) {
    objectiveForm.post(`/stages/fiches-entree/${props.form.id}/objectifs`, {
        preserveScroll: true,
        onSuccess: () => {
            objectiveForm.reset();
            addingObjective.value = another && props.readiness.objective_count < 3;
        },
    });
}

const inputClass = 'touch-target min-w-0 rounded-lg border border-separator px-3';
const areaClass = 'min-w-0 rounded-lg border border-separator p-3';
</script>

<template>
    <Head :title="`Fiche d’entrée de ${form.candidate}`" />
    <AppLayout :title="`Fiche d’entrée de ${form.candidate}`" active-navigation="internship">
        <div class="grid min-w-0 gap-5">
            <PageHeader
                eyebrow="Mise en route d’un stagiaire"
                :title="`Fiche d’entrée de ${form.candidate}`"
                :description="`${form.state_label} — ${form.duration_weeks} semaines. Responsable : ${form.manager}. Tuteur : ${form.tutor || 'à désigner'}.`"
            >
                <template #actions>
                    <AppButton variant="secondaire" href="/stages/fiches-entree">Toutes les fiches</AppButton>
                </template>
            </PageHeader>

            <p v-if="already_activated" class="rounded-xl border border-success bg-success-soft p-4 font-medium text-success" role="status">
                ✓ {{ form.candidate }} est déjà activé : son stage est ouvert, il n’y a plus rien à activer.
            </p>

            <ol class="grid min-w-0 gap-4" aria-label="Étapes de mise en route">
                <!-- Étape 1 — la fiche -->
                <li class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4 sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-section-title">1. Fiche d’entrée</h2>
                        <span class="text-sm font-semibold" :class="formDone ? 'text-success' : 'text-ink-secondary'">
                            {{ formDone ? '✓ Approuvée' : form.state_label }}
                        </span>
                    </div>

                    <dl class="grid min-w-0 gap-3 sm:grid-cols-3">
                        <div class="grid gap-0.5"><dt class="text-sm text-ink-muted">Besoin réel</dt><dd>{{ form.real_need }}</dd></div>
                        <div class="grid gap-0.5"><dt class="text-sm text-ink-muted">Mission</dt><dd>{{ form.mission }}</dd></div>
                        <div class="grid gap-0.5"><dt class="text-sm text-ink-muted">Outils</dt><dd>{{ form.tools }}</dd></div>
                    </dl>
                    <div class="grid gap-1">
                        <h3 class="text-sm text-ink-muted">Résultats attendus</h3>
                        <ol class="grid list-inside list-decimal gap-0.5">
                            <li v-for="outcome in form.outcomes" :key="outcome.id">{{ outcome.description }}</li>
                        </ol>
                    </div>

                    <p v-if="form.decided_at" class="text-sm text-ink-secondary">
                        {{ form.state_label }} par {{ form.decided_by }} le {{ form.decided_at }}.
                    </p>
                    <p v-if="form.decision_reason" class="text-sm text-ink-secondary">Motif : {{ form.decision_reason }}</p>
                    <p v-if="form.state === 'refusee' && !already_activated" class="rounded-lg border border-warning bg-warning-soft p-3 text-sm" role="note">
                        Une fiche refusée ne se modifie pas. Pour activer ce compte, rédigez une nouvelle fiche depuis
                        <a href="/stages/fiches-entree" class="font-semibold underline">Fiches d’entrée</a> (bouton « Rédiger une nouvelle fiche »).
                    </p>

                    <div v-if="permissions.submit || permissions.decide" class="flex flex-wrap gap-2 border-t border-separator pt-3">
                        <AppButton v-if="permissions.submit" variant="principal" :busy="submitForm.processing" busy-label="Envoi" @click="submitIntake">Soumettre à la direction</AppButton>
                        <template v-if="permissions.decide">
                            <AppButton variant="principal" :busy="decisionForm.processing && decisionForm.approved" busy-label="Approbation" @click="decide(true)">Approuver la fiche</AppButton>
                            <AppButton variant="secondaire" @click="refusing = true">Refuser…</AppButton>
                        </template>
                    </div>
                    <p v-if="permissions.submit" class="text-sm text-ink-muted">Une fois soumise, la direction l’approuve ici même.</p>
                </li>

                <!-- Étape 2 — les objectifs -->
                <li class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4 sm:p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-section-title">2. Trois objectifs</h2>
                        <span class="text-sm font-semibold" :class="objectivesDone ? 'text-success' : 'text-ink-secondary'">
                            {{ objectivesDone ? `✓ ${readiness.objective_count} enregistrés (3 attendus)` : `${readiness.objective_count} sur 3` }}
                        </span>
                    </div>
                    <ul v-if="objectives.length" class="grid min-w-0 gap-2">
                        <li v-for="objective in objectives" :key="objective.id" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-separator p-3">
                            <span class="font-medium">{{ objective.title }}</span>
                            <span class="text-sm text-ink-secondary">Échéance {{ objective.due_date }} · {{ objective.state_label }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-ink-secondary">Aucun objectif pour l’instant.</p>
                    <div v-if="permissions.add_objective && !already_activated" class="flex flex-wrap items-center gap-3">
                        <AppButton :variant="objectivesDone ? 'secondaire' : 'principal'" @click="openObjective">Ajouter un objectif</AppButton>
                        <span v-if="missingObjectives" class="text-sm text-ink-muted">Encore {{ missingObjectives }} à ajouter.</span>
                    </div>
                </li>

                <!-- Étape 3 — l'activation -->
                <li class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4 sm:p-5">
                    <h2 class="text-section-title">3. Activation du compte</h2>
                    <!-- Chaque condition est écrite en toutes lettres : l'information n'est jamais
                         portée par la couleur. -->
                    <ul class="grid gap-1 text-ink-secondary">
                        <li>Fiche d’entrée approuvée : {{ readiness.approved_intake_form ? 'oui' : 'non' }}</li>
                        <li>Tuteur désigné : {{ readiness.designated_tutor ? 'oui' : (form.tutor ? `${form.tutor}, dès l’approbation de la fiche` : 'non') }}</li>
                        <li>Trois objectifs enregistrés : {{ objectivesDone ? 'oui' : `${readiness.objective_count} sur 3` }}</li>
                    </ul>
                    <template v-if="!already_activated">
                        <p v-if="!readiness.satisfied" class="text-ink-secondary">
                            Activation impossible pour l’instant : {{ readiness.missing.join(', ') }}.
                        </p>
                        <p v-if="activationForm.errors.candidate || activationForm.errors.tutor_id" class="rounded-lg border border-danger bg-danger-soft p-3 text-danger" role="alert">
                            ⚠ {{ activationForm.errors.candidate || activationForm.errors.tutor_id }}
                        </p>
                        <p v-else-if="tutor_at_limit" class="rounded-lg border border-warning bg-warning-soft p-3" role="note">
                            ⚠ {{ tutor_at_limit }} Le tuteur d’une fiche approuvée ne se change pas : attendez qu’une place se libère
                            (fin d’un stage) ou rédigez une nouvelle fiche avec un autre tuteur.
                        </p>
                        <div v-if="readiness.satisfied && permissions.activate">
                            <AppButton variant="principal" :busy="activationForm.processing" :disabled="activationForm.processing" busy-label="Activation" @click="activateIntern">
                                Activer le compte du stagiaire
                            </AppButton>
                        </div>
                    </template>
                </li>
            </ol>

            <AppModal v-model:open="addingObjective" size="lg" :title="`Objectif pour ${form.candidate}`" :description="`Objectif ${Math.min(readiness.objective_count + 1, 3)} sur 3. Il est enregistré en brouillon, à valider ensuite depuis « Objectifs » par la direction ou par le responsable du stagiaire.`">
                <form id="intake-objective-form" class="grid min-w-0 gap-4 sm:grid-cols-2" @submit.prevent="saveObjective(false)">
                    <label class="grid gap-1 font-medium sm:col-span-2">Titre
                        <input v-model="objectiveForm.title" required maxlength="160" :class="inputClass">
                        <span v-if="objectiveForm.errors.title" class="text-sm text-danger">{{ objectiveForm.errors.title }}</span>
                    </label>
                    <label class="grid gap-1 font-medium sm:col-span-2">Description
                        <textarea v-model="objectiveForm.description" required maxlength="500" rows="2" :class="areaClass" />
                        <span v-if="objectiveForm.errors.description" class="text-sm text-danger">{{ objectiveForm.errors.description }}</span>
                    </label>
                    <label class="grid gap-1 font-medium">Indicateur
                        <input v-model="objectiveForm.indicator" required maxlength="160" placeholder="Ex. : pages transcrites" :class="inputClass">
                        <span v-if="objectiveForm.errors.indicator" class="text-sm text-danger">{{ objectiveForm.errors.indicator }}</span>
                    </label>
                    <label class="grid gap-1 font-medium">Valeur cible
                        <input v-model="objectiveForm.target_value" required maxlength="160" placeholder="Ex. : 100 pages" :class="inputClass">
                        <span v-if="objectiveForm.errors.target_value" class="text-sm text-danger">{{ objectiveForm.errors.target_value }}</span>
                    </label>
                    <label class="grid gap-1 font-medium sm:col-span-2">Preuve attendue
                        <input v-model="objectiveForm.expected_evidence" required maxlength="500" placeholder="Ex. : fichiers déposés et relus" :class="inputClass">
                        <span v-if="objectiveForm.errors.expected_evidence" class="text-sm text-danger">{{ objectiveForm.errors.expected_evidence }}</span>
                    </label>
                    <label class="grid gap-1 font-medium">Échéance
                        <input v-model="objectiveForm.due_date" required type="date" :class="inputClass">
                        <span v-if="objectiveForm.errors.due_date" class="text-sm text-danger">{{ objectiveForm.errors.due_date }}</span>
                    </label>
                    <label class="grid gap-1 font-medium">Priorité
                        <select v-model="objectiveForm.priority" :class="inputClass">
                            <option v-for="priority in priorities" :key="priority.value" :value="priority.value">{{ priority.label }}</option>
                        </select>
                    </label>
                    <p v-if="objectiveForm.errors.user_id" class="text-sm text-danger sm:col-span-2" role="alert">{{ objectiveForm.errors.user_id }}</p>
                </form>
                <template #footer>
                    <AppButton @click="addingObjective = false">Fermer</AppButton>
                    <AppButton v-if="readiness.objective_count < 2" variant="secondaire" :busy="objectiveForm.processing" @click="saveObjective(true)">Enregistrer et en ajouter un autre</AppButton>
                    <AppButton type="submit" form="intake-objective-form" variant="principal" :busy="objectiveForm.processing">Enregistrer</AppButton>
                </template>
            </AppModal>

            <AppModal v-model:open="refusing" title="Refuser la fiche" description="Le motif est obligatoire : il est montré à qui a rédigé la fiche.">
                <form id="intake-refusal-form" class="grid gap-2" @submit.prevent="decide(false)">
                    <label class="grid gap-1 font-medium" for="decision-reason">Motif du refus
                        <textarea id="decision-reason" v-model="decisionForm.decision_reason" required rows="3" :class="areaClass" />
                    </label>
                    <p v-if="decisionForm.errors.decision_reason" class="text-sm text-danger" role="alert">{{ decisionForm.errors.decision_reason }}</p>
                </form>
                <template #footer>
                    <AppButton @click="refusing = false">Annuler</AppButton>
                    <AppButton type="submit" form="intake-refusal-form" variant="danger" :busy="decisionForm.processing">Refuser la fiche</AppButton>
                </template>
            </AppModal>
        </div>
    </AppLayout>
</template>
