<script setup>
import { nextTick, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AttachmentUploader from '../../../Components/AttachmentUploader.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    myAbsences: { type: Array, required: true },
    pendingApprovals: { type: Array, required: true },
    visibleAbsences: { type: Array, required: true },
    types: { type: Array, required: true },
    attachment: { type: Object, required: true },
});

const now = new Date();
const today = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const refusalId = ref(null);
const refusalField = ref(null);
const form = useForm({ type: 'conge', start_date: today, end_date: today, reason: '', attachment_ulid: null });
const refusalForm = useForm({ decision_reason: '' });

function submit() {
    form.post('/absences', {
        onError: () => nextTick(() => document.querySelector('[aria-invalid="true"]')?.focus()),
    });
}

function approve(absence) {
    router.patch(`/absences/${absence.id}/approuver`, {}, { preserveScroll: true });
}

async function prepareRefusal(absence) {
    refusalId.value = absence.id;
    refusalForm.reset();
    await nextTick();
    refusalField.value?.focus();
}

function refuse() {
    refusalForm.patch(`/absences/${refusalId.value}/refuser`, {
        preserveScroll: true,
        onSuccess: () => { refusalId.value = null; },
        onError: () => nextTick(() => refusalField.value?.focus()),
    });
}

function cancel(absence) {
    router.patch(`/absences/${absence.id}/annuler`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Absences" back-label="Accueil" back-href="/" active-navigation="absences">
        <div class="grid min-w-0 gap-8">
            <header class="grid gap-2">
                <h1 class="text-page-title">Déclarer une absence</h1>
                <p class="max-w-2xl text-ink-secondary">Transmettez les dates et le motif à votre responsable direct. Le justificatif reste facultatif.</p>
            </header>

            <form class="grid min-w-0 gap-5 rounded-xl border border-separator bg-surface p-4 md:grid-cols-2" @submit.prevent="submit">
                <label class="grid gap-2" for="absence-type">
                    <span class="font-semibold">Type <span class="text-danger" aria-hidden="true">✱</span></span>
                    <select id="absence-type" v-model="form.type" required class="min-h-12 min-w-0 rounded-lg border border-separator bg-surface px-3 text-base" :aria-invalid="form.errors.type ? 'true' : 'false'">
                        <option v-for="type in types" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                    <span v-if="form.errors.type" class="text-sm font-semibold text-danger">⚠ {{ form.errors.type }}</span>
                </label>
                <div class="hidden md:block" aria-hidden="true" />
                <FormField id="absence-start-date" v-model="form.start_date" label="Date de début" variant="date" required :error="form.errors.start_date" />
                <FormField id="absence-end-date" v-model="form.end_date" label="Date de fin" variant="date" required :error="form.errors.end_date" />
                <label class="grid min-w-0 gap-2 md:col-span-2" for="absence-reason">
                    <span class="font-semibold">Motif court <span class="text-danger" aria-hidden="true">✱</span></span>
                    <textarea id="absence-reason" v-model="form.reason" rows="3" maxlength="255" required class="min-h-24 min-w-0 resize-y rounded-lg border border-separator bg-surface p-3 text-base" :aria-invalid="form.errors.reason ? 'true' : 'false'" />
                    <span v-if="form.errors.reason" class="text-sm font-semibold text-danger">⚠ {{ form.errors.reason }}</span>
                </label>
                <div class="min-w-0 md:col-span-2">
                    <AttachmentUploader
                        attachable-type="person"
                        :attachable-id="attachment.attachable_id"
                        :allowed-types="attachment.allowed_types"
                        :max-size-bytes="attachment.max_size_bytes"
                        @uploaded="form.attachment_ulid = $event.ulid"
                    />
                    <p v-if="form.errors.attachment_ulid" class="mt-2 text-sm font-semibold text-danger">⚠ {{ form.errors.attachment_ulid }}</p>
                </div>
                <AppButton type="submit" variant="principal" :busy="form.processing" busy-label="Envoi en cours">Envoyer la demande</AppButton>
            </form>

            <section class="grid min-w-0 gap-4" aria-labelledby="my-absences-title">
                <h2 id="my-absences-title" class="text-section-title">Mes absences</h2>
                <EmptyState v-if="myAbsences.length === 0" title="Aucune absence déclarée." reason="Votre première demande apparaîtra ici." />
                <ul v-else class="grid min-w-0 gap-3 sm:grid-cols-2">
                    <li v-for="absence in myAbsences" :key="absence.id" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                        <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                            <a :href="`/absences/${absence.id}`" class="min-w-0 break-words text-card-title text-primary">{{ absence.type_label }}</a>
                            <StatusBadge :status="absence.state" />
                        </div>
                        <p>{{ absence.display_period }}</p>
                        <p class="break-words text-sm text-ink-secondary">{{ absence.reason }}</p>
                        <AppButton v-if="absence.can_cancel" variant="destructeur" @click="cancel(absence)">Annuler la demande</AppButton>
                    </li>
                </ul>
            </section>

            <section class="grid min-w-0 gap-4" aria-labelledby="approvals-title">
                <h2 id="approvals-title" class="text-section-title">Demandes à approuver</h2>
                <EmptyState v-if="pendingApprovals.length === 0" title="Aucune demande à approuver." reason="Les demandes de votre équipe apparaîtront ici." />
                <ul v-else class="grid min-w-0 gap-3">
                    <li v-for="absence in pendingApprovals" :key="absence.id" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                        <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0"><h3 class="break-words text-card-title">{{ absence.user.name }} · {{ absence.type_label }}</h3><p>{{ absence.display_period }}</p></div>
                            <StatusBadge :status="absence.state" />
                        </div>
                        <p class="break-words">{{ absence.reason }}</p>
                        <div v-if="absence.can_approve" class="flex flex-wrap gap-2">
                            <AppButton variant="principal" @click="approve(absence)">Approuver</AppButton>
                            <AppButton variant="destructeur" @click="prepareRefusal(absence)">Refuser</AppButton>
                        </div>
                        <form v-if="refusalId === absence.id" class="grid min-w-0 gap-3 rounded-lg bg-danger-soft p-3" @submit.prevent="refuse">
                            <label class="grid gap-2" :for="`refusal-${absence.id}`">
                                <span class="font-semibold">Motif du refus</span>
                                <textarea :id="`refusal-${absence.id}`" ref="refusalField" v-model="refusalForm.decision_reason" rows="3" required maxlength="500" class="min-h-24 min-w-0 rounded-lg border border-danger bg-surface p-3 text-base" :aria-invalid="refusalForm.errors.decision_reason ? 'true' : 'false'" />
                                <span v-if="refusalForm.errors.decision_reason" class="text-sm font-semibold text-danger">⚠ {{ refusalForm.errors.decision_reason }}</span>
                            </label>
                            <div class="flex flex-wrap gap-2"><AppButton type="submit" variant="destructeur" :busy="refusalForm.processing">Confirmer le refus</AppButton><AppButton variant="secondaire" @click="refusalId = null">Fermer</AppButton></div>
                        </form>
                    </li>
                </ul>
            </section>

            <section class="grid min-w-0 gap-4" aria-labelledby="visible-absences-title">
                <h2 id="visible-absences-title" class="text-section-title">Absences accessibles</h2>
                <EmptyState v-if="visibleAbsences.length === 0" title="Aucune absence accessible." reason="Les absences autorisées par votre périmètre apparaîtront ici." />
                <ul v-else class="grid min-w-0 gap-3 sm:grid-cols-2">
                    <li v-for="absence in visibleAbsences" :key="absence.id" class="flex min-w-0 items-start justify-between gap-3 rounded-xl border border-separator bg-surface p-4">
                        <div class="min-w-0"><a :href="`/absences/${absence.id}`" class="break-words font-semibold text-primary">{{ absence.user.name }}</a><p class="text-sm">{{ absence.type_label }} · {{ absence.display_period }}</p></div>
                        <StatusBadge :status="absence.state" variant="compacte" />
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
