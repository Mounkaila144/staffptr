<script setup>
import { router, usePage } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({ shares: { type: Array, required: true }, ownScope: { type: Boolean, required: true } });
// Dates `AAAA-MM-JJ` du serveur affichées au format local JJ/MM/AAAA.
function formatDate(iso) {
    const [year, month, day] = String(iso ?? '').slice(0, 10).split('-');

    return day ? `${day}/${month}/${year}` : iso;
}
// Refus de la demande de versement (part soldée, demande déjà en cours…) : affiché en tête.
const page = usePage();
function requestPayment(share) { router.post(`/finances/parts/${share.id}/demander-versement`, {}, { preserveScroll: true }); }
</script>

<template>
    <AppLayout title="Parts" active-navigation="finance">
        <div class="grid min-w-0 gap-8">
            <header><h1 class="break-words text-page-title">Droits aux parts matérialisés</h1><p class="text-ink-secondary">Chaque ligne provient d’un encaissement réel et expose sa base, sa période, son taux et sa méthode.</p><p v-if="ownScope" class="mt-2 rounded-lg border border-primary bg-primary-soft p-3 font-semibold">Confidentialité : uniquement votre propre part est visible.</p></header>
            <p v-if="page.props.errors?.share" class="rounded-xl border border-danger/40 bg-danger-soft p-4 font-semibold text-danger" role="alert">⚠ {{ page.props.errors.share }}</p>
            <EmptyState v-if="shares.length === 0" title="Aucune part due à ce jour." reason="Une facture non encaissée ne crée aucun droit." />
            <ul v-else class="grid min-w-0 gap-4"><li v-for="share in shares" :key="share.id" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4"><div class="flex min-w-0 flex-wrap justify-between gap-2"><div><strong>{{ share.beneficiary_name }}</strong><p>{{ share.contract_reference }} · {{ share.client_name }}</p></div><span class="rounded-full bg-primary-soft px-3 py-1 font-semibold">{{ share.rate_label }}</span></div><dl class="grid gap-3 sm:grid-cols-3"><div><dt class="text-sm text-ink-secondary">Droit calculé</dt><dd class="font-bold">{{ share.share_amount_label }}</dd></div><div><dt class="text-sm text-ink-secondary">Déjà versé</dt><dd class="font-bold">{{ share.paid_amount_label }}</dd></div><div><dt class="text-sm text-ink-secondary">Restant à verser</dt><dd class="font-bold">{{ share.remaining_amount_label }}</dd></div></dl><p class="rounded-lg bg-neutral-soft p-3 text-sm"><strong>Méthode :</strong> {{ share.calculation_method }}<br>Base {{ share.base_amount_label }} · période du {{ formatDate(share.period_start) }} au {{ formatDate(share.period_end) }} · reçu {{ share.receipt_number }} du {{ formatDate(share.source_received_on) }}.</p><div class="flex flex-wrap gap-2"><AppButton variant="secondaire" :href="`/finances/parts/${share.id}`">Voir la ligne</AppButton><AppButton v-if="share.can_request_payment" variant="principal" @click="requestPayment(share)">Demander le versement</AppButton><span v-else-if="share.payment_requested" class="self-center text-sm font-semibold text-ink-secondary">⏳ Demande de versement en cours dans les dépenses</span></div></li></ul>
        </div>
    </AppLayout>
</template>
