<script setup>
import { nextTick, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({ absence: { type: Object, required: true } });
const refusing = ref(false);
const refusalField = ref(null);
const refusalForm = useForm({ decision_reason: '' });

function approve() { router.patch(`/absences/${props.absence.id}/approuver`); }
function cancel() { router.patch(`/absences/${props.absence.id}/annuler`); }
async function openRefusal() { refusing.value = true; await nextTick(); refusalField.value?.focus(); }
function refuse() {
    refusalForm.patch(`/absences/${props.absence.id}/refuser`, { onError: () => nextTick(() => refusalField.value?.focus()) });
}
</script>

<template>
    <AppLayout title="Détail de l’absence" back-label="Absences" back-href="/absences" active-navigation="absences">
        <article class="grid min-w-0 gap-6 rounded-xl border border-separator bg-surface p-4 sm:p-6">
            <header class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                <div class="min-w-0"><h1 class="break-words text-page-title">{{ absence.user.name }}</h1><p class="text-ink-secondary">{{ absence.type_label }} · {{ absence.display_period }}</p></div>
                <StatusBadge :status="absence.state" />
            </header>
            <dl class="grid min-w-0 gap-4 sm:grid-cols-2">
                <div><dt class="text-sm font-semibold text-ink-secondary">Motif</dt><dd class="break-words">{{ absence.reason }}</dd></div>
                <div><dt class="text-sm font-semibold text-ink-secondary">Responsable direct</dt><dd>{{ absence.manager_name || 'Non renseigné' }}</dd></div>
                <div v-if="absence.decided_by_name"><dt class="text-sm font-semibold text-ink-secondary">Décision prise par</dt><dd>{{ absence.decided_by_name }}</dd></div>
                <div v-if="absence.decision_reason"><dt class="text-sm font-semibold text-ink-secondary">Motif du refus</dt><dd class="break-words">{{ absence.decision_reason }}</dd></div>
            </dl>
            <a v-if="absence.attachment" :href="absence.attachment.url" class="touch-target inline-flex w-fit items-center font-semibold text-primary underline-offset-4 hover:underline">Ouvrir le justificatif : {{ absence.attachment.name }}</a>
            <div class="flex flex-wrap gap-2">
                <AppButton v-if="absence.can_approve" variant="principal" @click="approve">Approuver</AppButton>
                <AppButton v-if="absence.can_refuse" variant="destructeur" @click="openRefusal">Refuser</AppButton>
                <AppButton v-if="absence.can_cancel" variant="destructeur" @click="cancel">Annuler la demande</AppButton>
            </div>
            <form v-if="refusing" class="grid min-w-0 gap-3 rounded-lg bg-danger-soft p-3" @submit.prevent="refuse">
                <label class="grid gap-2" for="absence-refusal"><span class="font-semibold">Motif du refus</span><textarea id="absence-refusal" ref="refusalField" v-model="refusalForm.decision_reason" rows="3" required maxlength="500" class="min-h-24 min-w-0 rounded-lg border border-danger bg-surface p-3 text-base" :aria-invalid="refusalForm.errors.decision_reason ? 'true' : 'false'" /></label>
                <p v-if="refusalForm.errors.decision_reason" class="text-sm font-semibold text-danger">⚠ {{ refusalForm.errors.decision_reason }}</p>
                <AppButton type="submit" variant="destructeur" :busy="refusalForm.processing">Confirmer le refus</AppButton>
            </form>
        </article>
    </AppLayout>
</template>
