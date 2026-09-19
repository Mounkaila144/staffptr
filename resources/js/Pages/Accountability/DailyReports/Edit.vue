<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { nextTick, onMounted, reactive, ref } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useDraft } from '../../../Composables/useDraft.js';

const props = defineProps({
    daily: { type: Object, required: true },
    userId: { type: Number, required: true },
    attachmentConfig: { type: Object, required: true },
    solicitableUsers: { type: Array, required: true },
});

const current = props.daily.report?.version;
const fields = reactive({
    planned_task: current?.planned_task || props.daily.planned_task || '',
    achieved_result: current?.achieved_result || '',
    evidence_link: current?.evidence_link || '',
    blocker_present: current?.blocker_present ?? false,
    blocker_details: current?.blocker_details || '',
    next_action: current?.next_action || '',
    help_requested: current?.help_requested ?? false,
    help_details: current?.help_details || '',
    lateness_explanation: props.daily.report?.lateness_explanation || '',
    correction_reason: '',
});
const errors = ref({});
const processing = ref(false);
const offline = ref(false);
const uploadError = ref('');
const uploadName = ref(current?.attachment_name || '');
const attachmentUlid = ref('');
const uploading = ref(false);
const idempotencyKey = ref(globalThis.crypto?.randomUUID?.() || `${Date.now()}-0000-4000-8000-${Date.now()}`);
const taskRequestOpen = ref(false);
const taskRequestForm = useForm({ description: '', is_urgent: false });
const blockerOpen = ref(false);
const blockerForm = useForm({ origin_type: 'daily_report', origin_id: props.daily.report?.id || null, problem: '', urgency: 'normale', solicited_user_id: '', reported_on: props.daily.date, deadline_impact: '', attempted_action: '' });
const { purge, restore, restoredLabel, saveNow, savedLabel } = useDraft(
    'daily-report',
    props.userId,
    props.daily.date,
    fields,
);

onMounted(() => {
    const draft = restore();
    if (draft && typeof draft === 'object') {
        Object.assign(fields, draft);
    }
});

function xsrfToken() {
    const cookie = document.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.substring('XSRF-TOKEN='.length)) : '';
}

async function uploadProof(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    uploading.value = true;
    uploadError.value = '';
    const payload = new FormData();
    payload.append('attachable_type', 'person');
    payload.append('attachable_id', String(props.attachmentConfig.attachable_id));
    payload.append('file', file);

    try {
        const response = await fetch('/internal/v1/attachments', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
            body: payload,
        });
        const result = await response.json();
        if (!response.ok) {
            throw new Error(result.errors?.file?.[0] || "Le fichier n'a pas pu être envoyé. Réessayez.");
        }
        attachmentUlid.value = result.attachment.ulid;
        uploadName.value = result.attachment.original_name;
    } catch (error) {
        uploadError.value = navigator.onLine
            ? error.message
            : "Le fichier n'a pas pu être envoyé — pas de connexion. Réessayez lorsque la connexion revient.";
    } finally {
        uploading.value = false;
    }
}

function submit() {
    saveNow();
    offline.value = !navigator.onLine;
    if (offline.value) return;

    processing.value = true;
    errors.value = {};
    router.post('/rapports/quotidiens', {
        ...fields,
        attachment_ulid: attachmentUlid.value || null,
        idempotency_key: idempotencyKey.value,
    }, {
        preserveScroll: true,
        onError: async (validationErrors) => {
            errors.value = validationErrors;
            await nextTick();
            document.querySelector('[data-field-error]')?.closest('label, fieldset')?.querySelector('input, textarea')?.focus();
        },
        onSuccess: () => {
            purge();
            idempotencyKey.value = globalThis.crypto?.randomUUID?.() || idempotencyKey.value;
        },
        onFinish: () => { processing.value = false; },
    });
}

function submitTaskRequest() {
    taskRequestForm.post(`/rapports/${props.daily.report.id}/demandes-tache`, {
        preserveScroll: true,
        onSuccess: () => { taskRequestOpen.value = false; taskRequestForm.reset(); },
    });
}

function submitBlocker() {
    blockerForm.post('/blocages', { preserveScroll: true, onSuccess: () => { blockerOpen.value = false; blockerForm.reset(); } });
}
</script>

<template>
    <Head title="Mon rapport du jour" />
    <AppLayout title="Mon rapport du jour" active-navigation="daily-reports">
        <div class="mx-auto grid w-full min-w-0 max-w-3xl gap-5" aria-labelledby="daily-report-title">
            <header class="grid min-w-0 gap-2">
                <h1 id="daily-report-title" class="break-words text-screen-title">Mon rapport du jour</h1>
                <p class="text-ink-secondary">{{ daily.date }} · Les réponses « Non » sont enregistrées comme telles.</p>
                <p v-if="daily.report" class="font-semibold">{{ daily.report.state_label }} · version {{ daily.report.version?.number }}</p>
            </header>

            <div v-if="!daily.expected" class="rounded-xl border border-separator bg-neutral-soft p-4" role="status">
                <p>{{ daily.unavailable_message }}</p>
                <Link href="/" class="touch-target mt-3 inline-flex items-center font-semibold text-primary">Retour à l’accueil</Link>
            </div>

            <form v-else class="grid min-w-0 gap-5" novalidate @submit.prevent="submit" @focusout="saveNow()">
                <div v-if="offline" class="grid gap-3 rounded-xl border-2 border-danger p-4" role="alert">
                    <p>L'envoi n'a pas abouti — pas de connexion. Votre rapport est conservé sur cet appareil.</p>
                    <button type="button" class="touch-target w-fit rounded-lg border border-primary px-4 font-semibold text-primary" @click="submit">Réessayer</button>
                </div>

                <p v-if="restoredLabel" class="rounded-lg bg-neutral-soft p-3" role="status">{{ restoredLabel }}</p>

                <section class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" aria-labelledby="proof-title">
                    <h2 id="proof-title" class="text-section-title">Preuve du travail</h2>
                    <label class="grid min-w-0 gap-2 font-semibold">
                        Lien vers une preuve
                        <input v-model="fields.evidence_link" type="url" inputmode="url" class="touch-target min-w-0 rounded-lg border border-separator px-3" placeholder="https://…" :aria-invalid="Boolean(errors.evidence_link)" />
                        <span v-if="errors.evidence_link" data-field-error class="text-sm text-danger">{{ errors.evidence_link }}</span>
                    </label>
                    <div class="grid min-w-0 gap-2">
                        <label for="daily-proof" class="font-semibold">Ou ajouter une image ou un document</label>
                        <input id="daily-proof" type="file" accept="image/jpeg,image/png,image/webp,image/heic,application/pdf" class="touch-target min-w-0" @change="uploadProof" />
                        <p v-if="uploading" role="status">Envoi du fichier en cours…</p>
                        <p v-else-if="uploadName" class="break-all text-sm">Fichier prêt : {{ uploadName }}</p>
                        <p v-if="uploadError" class="text-sm text-danger" role="alert">{{ uploadError }}</p>
                    </div>
                </section>

                <section class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" aria-labelledby="work-title">
                    <h2 id="work-title" class="text-section-title">Contribution du jour</h2>
                    <label class="grid min-w-0 gap-2 font-semibold">Tâche prévue
                        <textarea v-model="fields.planned_task" rows="4" class="min-w-0 rounded-lg border border-separator p-3" :aria-invalid="Boolean(errors.planned_task)" />
                        <span v-if="errors.planned_task" data-field-error class="text-sm text-danger">{{ errors.planned_task }}</span>
                    </label>
                    <label class="grid min-w-0 gap-2 font-semibold">Résultat obtenu
                        <textarea v-model="fields.achieved_result" rows="4" class="min-w-0 rounded-lg border border-separator p-3" :aria-invalid="Boolean(errors.achieved_result)" />
                        <span v-if="errors.achieved_result" data-field-error class="text-sm text-danger">{{ errors.achieved_result }}</span>
                    </label>
                    <label class="grid min-w-0 gap-2 font-semibold">Prochaine action
                        <textarea v-model="fields.next_action" rows="3" class="min-w-0 rounded-lg border border-separator p-3" :aria-invalid="Boolean(errors.next_action)" />
                        <span v-if="errors.next_action" data-field-error class="text-sm text-danger">{{ errors.next_action }}</span>
                    </label>
                </section>

                <fieldset class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                    <legend class="px-1 font-semibold">Avez-vous rencontré un blocage ?</legend>
                    <div class="flex flex-wrap gap-4">
                        <label class="touch-target inline-flex items-center gap-2"><input v-model="fields.blocker_present" type="radio" :value="false" /> Non</label>
                        <label class="touch-target inline-flex items-center gap-2"><input v-model="fields.blocker_present" type="radio" :value="true" /> Oui</label>
                    </div>
                    <label v-if="fields.blocker_present" class="grid min-w-0 gap-2 font-semibold">Décrivez le blocage
                        <textarea v-model="fields.blocker_details" rows="3" class="min-w-0 rounded-lg border border-separator p-3" />
                        <span v-if="errors.blocker_details" data-field-error class="text-sm text-danger">{{ errors.blocker_details }}</span>
                    </label>
                </fieldset>

                <fieldset class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                    <legend class="px-1 font-semibold">Demandez-vous de l’aide ?</legend>
                    <div class="flex flex-wrap gap-4">
                        <label class="touch-target inline-flex items-center gap-2"><input v-model="fields.help_requested" type="radio" :value="false" /> Non</label>
                        <label class="touch-target inline-flex items-center gap-2"><input v-model="fields.help_requested" type="radio" :value="true" /> Oui</label>
                    </div>
                    <label v-if="fields.help_requested" class="grid min-w-0 gap-2 font-semibold">Précisez l’aide attendue
                        <textarea v-model="fields.help_details" rows="3" class="min-w-0 rounded-lg border border-separator p-3" />
                        <span v-if="errors.help_details" data-field-error class="text-sm text-danger">{{ errors.help_details }}</span>
                    </label>
                </fieldset>

                <label v-if="daily.report?.state === 'en_retard'" class="grid min-w-0 gap-2 font-semibold">Explication courte du retard (facultative)
                    <textarea v-model="fields.lateness_explanation" rows="2" class="min-w-0 rounded-lg border border-separator p-3" />
                </label>

                <label v-if="daily.report" class="grid min-w-0 gap-2 font-semibold">Motif de la correction
                    <textarea v-model="fields.correction_reason" rows="2" required class="min-w-0 rounded-lg border border-separator p-3" :aria-invalid="Boolean(errors.correction_reason)" />
                    <span v-if="errors.correction_reason" data-field-error class="text-sm text-danger">{{ errors.correction_reason }}</span>
                </label>

                <section v-if="daily.report" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4" aria-labelledby="task-request-title">
                    <div class="flex flex-wrap items-center justify-between gap-2"><h2 id="task-request-title" class="text-section-title">Besoin d’une nouvelle tâche ?</h2><button type="button" class="touch-target rounded-lg border border-primary px-4 font-semibold text-primary" @click="taskRequestOpen = !taskRequestOpen">Demander une tâche</button></div>
                    <div v-if="taskRequestOpen" class="grid min-w-0 gap-3">
                        <label class="grid gap-2 font-semibold">Précisez votre besoin<textarea v-model="taskRequestForm.description" rows="3" required class="min-w-0 rounded-lg border border-separator p-3" /></label>
                        <label class="touch-target inline-flex items-center gap-2"><input v-model="taskRequestForm.is_urgent" type="checkbox" /> Cette demande est urgente</label>
                        <p v-if="taskRequestForm.errors.description" class="text-danger" role="alert">{{ taskRequestForm.errors.description }}</p>
                        <button type="button" class="touch-target rounded-lg bg-primary px-4 font-bold text-white" :disabled="taskRequestForm.processing" @click="submitTaskRequest">Transmettre à mon responsable</button>
                    </div>
                </section>

                <section v-if="daily.report" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4" aria-labelledby="report-blocker-title">
                    <div class="flex flex-wrap items-center justify-between gap-2"><h2 id="report-blocker-title" class="text-section-title">Un blocage à signaler ?</h2><button type="button" class="touch-target rounded-lg border border-primary px-4 font-semibold text-primary" @click="blockerOpen = !blockerOpen">Signaler sans quitter le rapport</button></div>
                    <div v-if="blockerOpen" class="grid min-w-0 gap-3 sm:grid-cols-2">
                        <label class="grid gap-1 font-semibold sm:col-span-2">Problème<textarea v-model="blockerForm.problem" rows="3" class="rounded-lg border border-separator p-3" /></label>
                        <label class="grid gap-1 font-semibold">Urgence<select v-model="blockerForm.urgency" class="touch-target rounded-lg border border-separator px-3"><option value="normale">Normale</option><option value="urgente">Urgente</option></select></label>
                        <label class="grid gap-1 font-semibold">Personne sollicitée<select v-model="blockerForm.solicited_user_id" class="touch-target rounded-lg border border-separator px-3"><option value="">Choisir</option><option v-for="user in solicitableUsers" :key="user.id" :value="user.id">{{ user.name }}</option></select></label>
                        <label class="grid gap-1 font-semibold">Effet sur l’échéance<textarea v-model="blockerForm.deadline_impact" class="rounded-lg border border-separator p-3" /></label>
                        <label class="grid gap-1 font-semibold">Action déjà essayée<textarea v-model="blockerForm.attempted_action" class="rounded-lg border border-separator p-3" /></label>
                        <button type="button" class="touch-target rounded-lg bg-primary px-4 font-bold text-white sm:col-span-2" :disabled="blockerForm.processing" @click="submitBlocker">Notifier la personne sollicitée</button>
                    </div>
                </section>

                <div class="sticky bottom-0 grid gap-2 border-t border-separator bg-surface py-3">
                    <p class="min-h-6 text-sm text-ink-secondary" aria-live="polite">{{ savedLabel }}</p>
                    <button type="submit" :disabled="processing || uploading" :aria-busy="processing" class="touch-target w-full rounded-lg bg-primary px-5 font-bold text-white disabled:opacity-60">
                        Envoyer mon rapport
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
