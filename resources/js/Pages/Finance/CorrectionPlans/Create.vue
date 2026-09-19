<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AlertLevelCard from '../../../Components/AlertLevelCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    month: { type: String, required: true },
    alert: { type: Object, required: true },
    dueLabel: { type: String, required: true },
    existingPlan: { type: Boolean, default: false },
});

// Les cinq champs de l'AC 14, dans l'ordre où on les remplit.
const form = useForm({
    month: props.month,
    finding: '',
    actions: '',
    responsibles: '',
    due_on: '',
    expected_result: '',
});

const longFields = [
    { key: 'finding', id: 'plan-finding', label: 'Constat', hint: "Ce qui a fait passer le mois sous l'assiette des charges fixes." },
    { key: 'actions', id: 'plan-actions', label: 'Actions', hint: 'Ce qui sera fait, concrètement.' },
    { key: 'expected_result', id: 'plan-expected-result', label: 'Résultat attendu', hint: 'Ce à quoi on saura que le plan a fonctionné.' },
];

function submit() {
    form.post('/finances/plans-correctifs', { preserveScroll: true });
}
</script>

<template>
    <Head title="Enregistrer un plan correctif" />
    <AppLayout title="Plan correctif" active-navigation="finance" back-label="Retour aux plans" back-href="/finances/plans-correctifs">
        <div class="grid min-w-0 gap-6">
            <header class="grid min-w-0 gap-2">
                <h1 class="break-words text-page-title">Enregistrer le plan correctif</h1>
                <p class="text-ink-secondary">Mois de {{ alert.month_label }} · à enregistrer avant le {{ dueLabel }}.</p>
            </header>

            <AlertLevelCard :alert="alert" />

            <p v-if="existingPlan" class="rounded-lg border border-warning bg-warning-soft p-3">
                <span aria-hidden="true">▲ </span>Un plan existe déjà pour ce mois. Il ne peut pas être remplacé : enregistrez une révision depuis la liste.
            </p>

            <form v-else class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" @submit.prevent="submit">
                <!--
                    Les trois champs longs sont des `textarea` : chaque libellé est associé à son
                    champ, l'aide est reliée par `aria-describedby` et l'erreur s'affiche sous le
                    champ concerné, sans terme technique (SOC-08, SOC-10).
                -->
                <div v-for="field in longFields" :key="field.id" class="grid min-w-0 gap-2">
                    <label :for="field.id" class="text-field-label font-semibold">
                        {{ field.label }} <span aria-hidden="true" class="text-danger">✱</span><span class="sr-only"> obligatoire</span>
                    </label>
                    <p :id="`${field.id}-hint`" class="text-sm text-ink-secondary">{{ field.hint }}</p>
                    <textarea
                        :id="field.id"
                        v-model="form[field.key]"
                        :name="field.id"
                        rows="4"
                        required
                        :aria-invalid="form.errors[field.key] ? 'true' : 'false'"
                        :aria-describedby="form.errors[field.key] ? `${field.id}-error` : `${field.id}-hint`"
                        class="min-h-24 w-full min-w-0 rounded-lg border bg-surface px-3 py-2 text-base"
                        :class="form.errors[field.key] ? 'border-2 border-danger' : 'border-separator'"
                    ></textarea>
                    <p v-if="form.errors[field.key]" :id="`${field.id}-error`" class="text-sm font-semibold text-danger">
                        ⚠ {{ form.errors[field.key] }}
                    </p>
                </div>

                <FormField
                    id="plan-responsibles"
                    v-model="form.responsibles"
                    label="Responsables"
                    placeholder="Qui porte chaque action"
                    required
                    :error="form.errors.responsibles"
                />
                <FormField
                    id="plan-due-on"
                    v-model="form.due_on"
                    variant="date"
                    label="Échéance"
                    required
                    :error="form.errors.due_on"
                />

                <AppButton type="submit" variant="principal" :busy="form.processing">Enregistrer le plan</AppButton>
            </form>
        </div>
    </AppLayout>
</template>
