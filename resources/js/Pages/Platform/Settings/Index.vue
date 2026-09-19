<script setup>
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import FormField from '../../../Components/FormField.vue';
import SensitiveConfirmation from '../../../Components/SensitiveConfirmation.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    settings: { type: Array, required: true },
    workingDayOptions: { type: Array, required: true },
    attachmentTypeOptions: { type: Array, required: true },
});

const initialValues = Object.fromEntries(props.settings.map((setting) => [setting.key, setting.value]));
const form = useForm({
    settings: JSON.parse(JSON.stringify(initialValues)),
    effective_at: localDateTime(),
});
const preview = ref(null);
const previewQueue = ref([]);
const previewIndex = ref(0);
const previewLoading = ref(false);
const previewError = ref('');

const scalarSettings = computed(() => props.settings.filter((setting) => setting.type !== 'array'));
const previewSetting = computed(() => preview.value
    ? props.settings.find((setting) => setting.key === preview.value.key)
    : null);

function localDateTime() {
    const now = new Date();
    const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
    return local.toISOString().slice(0, 16);
}

function fieldError(key) {
    return form.errors[`settings.${key}`] ?? '';
}

function changedNumericKeys() {
    return props.settings
        .filter((setting) => setting.numeric)
        .filter((setting) => Number(form.settings[setting.key]) !== Number(initialValues[setting.key]))
        .map((setting) => setting.key);
}

function requestSave() {
    previewQueue.value = changedNumericKeys();
    previewIndex.value = 0;
    previewError.value = '';

    if (previewQueue.value.length === 0) {
        submitSettings();
        return;
    }

    loadCurrentPreview();
}

async function loadCurrentPreview() {
    const key = previewQueue.value[previewIndex.value];
    previewLoading.value = true;
    previewError.value = '';

    try {
        const response = await fetch('/parametres/apercu', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ key, value: form.settings[key] }),
        });
        const payload = await response.json();

        if (!response.ok) {
            const message = payload.errors?.value?.[0] ?? "L’aperçu n’a pas pu être calculé. Vérifiez cette valeur.";
            form.setError(`settings.${key}`, message);
            nextTick(() => document.getElementById(`setting-${key}`)?.focus());
            return;
        }

        preview.value = payload.preview;
    } catch {
        previewError.value = "L’aperçu n’a pas pu être calculé. Réessayez avant d’enregistrer.";
    } finally {
        previewLoading.value = false;
    }
}

function confirmPreview() {
    preview.value = null;
    previewIndex.value += 1;

    if (previewIndex.value < previewQueue.value.length) {
        loadCurrentPreview();
        return;
    }

    submitSettings();
}

function cancelPreview() {
    preview.value = null;
    previewQueue.value = [];
    previewIndex.value = 0;
}

function submitSettings() {
    form.patch('/parametres', {
        preserveScroll: true,
        onError: (errors) => {
            const field = Object.keys(errors)[0];
            const key = field?.replace('settings.', '');
            nextTick(() => document.getElementById(key === 'effective_at' ? 'effective_at' : `setting-${key}`)?.focus());
        },
    });
}

function previewAmount() {
    const unit = previewSetting.value?.unit ?? '';
    return `${preview.value?.proposed_value ?? ''} ${unit}`.trim();
}

function previewDifference() {
    const delta = preview.value?.delta;
    const unit = previewSetting.value?.unit ?? 'unités';

    if (delta === null || delta === undefined) return 'Effet calculé avant enregistrement';
    return `Écart calculé : ${delta > 0 ? '+' : ''}${delta} ${unit}`;
}
</script>

<template>
    <AppLayout title="Paramètres généraux" back-label="Accueil" active-navigation="settings">
        <div class="grid min-w-0 gap-8">
            <header class="grid min-w-0 gap-3">
                <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                    <h1 class="break-words text-page-title">Règles générales</h1>
                    <StatusBadge status="actif" />
                </div>
                <p class="text-ink-secondary">Adaptez les règles chiffrées sans redéploiement. Chaque changement est daté et conservé dans le journal d’audit.</p>
                <a href="/categories-depense" class="touch-target inline-flex w-fit items-center font-semibold text-primary underline-offset-4 hover:underline">Gérer les catégories de dépense</a>
            </header>

            <p v-if="previewError" role="alert" class="rounded-lg border border-danger bg-danger-soft p-3 font-semibold text-danger">⚠ {{ previewError }}</p>
            <p v-if="previewLoading" aria-live="polite" class="rounded-lg border border-separator bg-neutral-soft p-3">Calcul de l’effet proposé…</p>

            <SensitiveConfirmation
                v-if="preview"
                :amount="previewAmount()"
                :amount-words="previewDifference()"
                :counterparty="preview.label"
                reason="Nouvelle règle générale proposée"
                :consequence="preview.consequence"
                :action-label="previewIndex + 1 < previewQueue.length ? 'Voir l’effet suivant' : 'Confirmer et enregistrer'"
                @confirm="confirmPreview"
                @cancel="cancelPreview"
            />

            <form v-else class="grid min-w-0 gap-8" @submit.prevent="requestSave">
                <section class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" aria-labelledby="schedule-settings-title">
                    <div class="grid gap-1">
                        <h2 id="schedule-settings-title" class="text-section-title">Organisation du temps</h2>
                        <p class="text-sm text-ink-secondary">Définissez les jours habituels, l’heure limite et le rappel des rapports.</p>
                    </div>
                    <fieldset class="grid min-w-0 gap-3">
                        <legend class="font-semibold">Jours travaillés</legend>
                        <div class="grid min-w-0 gap-2 sm:grid-cols-2">
                            <label v-for="day in workingDayOptions" :key="day.value" class="touch-target flex min-w-0 items-center gap-3 rounded-lg border border-separator px-3">
                                <input v-model="form.settings.working_days" type="checkbox" :value="day.value"> {{ day.label }}
                            </label>
                        </div>
                        <p v-if="fieldError('working_days')" class="text-sm font-semibold text-danger">⚠ {{ fieldError('working_days') }}</p>
                    </fieldset>
                </section>

                <section class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" aria-labelledby="numeric-settings-title">
                    <div class="grid gap-1">
                        <h2 id="numeric-settings-title" class="text-section-title">Valeurs et limites</h2>
                        <p class="text-sm text-ink-secondary">Les effets chiffrés modifiés seront présentés avant la confirmation finale.</p>
                    </div>
                    <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                        <FormField
                            v-for="setting in scalarSettings"
                            :id="`setting-${setting.key}`"
                            :key="setting.key"
                            v-model="form.settings[setting.key]"
                            :label="setting.unit ? `${setting.label} (${setting.unit})` : setting.label"
                            :variant="setting.type === 'time' ? 'time' : 'number'"
                            :error="fieldError(setting.key)"
                            required
                        />
                    </div>
                </section>

                <section class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" aria-labelledby="attachment-settings-title">
                    <div class="grid gap-1">
                        <h2 id="attachment-settings-title" class="text-section-title">Pièces jointes</h2>
                        <p class="text-sm text-ink-secondary">Choisissez les formats qui pourront être acceptés par les futurs écrans de dépôt.</p>
                    </div>
                    <fieldset class="grid min-w-0 gap-3">
                        <legend class="font-semibold">Types autorisés</legend>
                        <div class="grid min-w-0 gap-2 sm:grid-cols-2">
                            <label v-for="type in attachmentTypeOptions" :key="type" class="touch-target flex min-w-0 items-center gap-3 rounded-lg border border-separator px-3 uppercase">
                                <input v-model="form.settings.attachment_allowed_types" type="checkbox" :value="type"> {{ type }}
                            </label>
                        </div>
                        <p v-if="fieldError('attachment_allowed_types')" class="text-sm font-semibold text-danger">⚠ {{ fieldError('attachment_allowed_types') }}</p>
                    </fieldset>
                </section>

                <section class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4" aria-labelledby="effective-date-title">
                    <div class="grid gap-1">
                        <h2 id="effective-date-title" class="text-section-title">Date d’effet</h2>
                        <p class="text-sm text-ink-secondary">La règle s’applique immédiatement ; cette date permet de situer précisément la décision.</p>
                    </div>
                    <FormField id="effective_at" v-model="form.effective_at" label="Date et heure d’effet" variant="datetime-local" :error="form.errors.effective_at" required />
                </section>

                <AppButton type="submit" variant="principal" :busy="form.processing || previewLoading" busy-label="Préparation de la confirmation">Vérifier puis enregistrer</AppButton>
            </form>
        </div>
    </AppLayout>
</template>
