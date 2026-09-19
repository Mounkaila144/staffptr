<script setup>
import { router } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
defineProps({ share: { type: Object, required: true } });
</script>

<template>
    <AppLayout title="Détail de la part" back-label="Retour aux parts" back-href="/finances/parts" active-navigation="finance">
        <article class="grid min-w-0 gap-5 rounded-xl border border-separator bg-surface p-4"><header><p class="text-sm font-semibold text-ink-secondary">{{ share.contract_reference }} · {{ share.receipt_number }}</p><h1 class="break-words text-page-title">Part de {{ share.beneficiary_name }}</h1></header><dl class="grid gap-3 sm:grid-cols-2"><div><dt>Base</dt><dd class="font-bold">{{ share.base_amount_label }}</dd></div><div><dt>Taux</dt><dd class="font-bold">{{ share.rate_label }}</dd></div><div><dt>Montant</dt><dd class="font-bold">{{ share.share_amount_label }}</dd></div><div><dt>Restant</dt><dd class="font-bold">{{ share.remaining_amount_label }}</dd></div></dl><p class="rounded-lg bg-neutral-soft p-3">{{ share.calculation_method }}</p><AppButton v-if="share.can_request_payment" variant="principal" @click="router.post(`/finances/parts/${share.id}/demander-versement`)">Demander le versement via une dépense ordinaire</AppButton></article>
    </AppLayout>
</template>
