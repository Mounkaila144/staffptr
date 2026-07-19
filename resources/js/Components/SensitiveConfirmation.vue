<script setup>
import AppButton from './AppButton.vue';

defineProps({
    amount: { type: String, required: true },
    amountWords: { type: String, required: true },
    counterparty: { type: String, required: true },
    reason: { type: String, required: true },
    consequence: { type: String, required: true },
    actionLabel: { type: String, required: true },
});
defineEmits(['confirm', 'cancel']);
</script>

<template>
    <section class="grid gap-4 rounded-xl border-2 border-warning bg-warning-soft p-4" aria-labelledby="sensitive-title">
        <h2 id="sensitive-title" class="text-section-title">Confirmer cette opération sensible</h2>
        <p class="text-amount">{{ amount }}</p>
        <p class="font-semibold">{{ amountWords }}</p>
        <dl class="grid gap-3"><div><dt class="text-sm text-ink-muted">Contrepartie</dt><dd class="font-semibold">{{ counterparty }}</dd></div><div><dt class="text-sm text-ink-muted">Motif</dt><dd>{{ reason }}</dd></div></dl>
        <p class="rounded-lg border border-warning p-3"><strong>Conséquence :</strong> {{ consequence }}</p>
        <div class="flex flex-col gap-2 sm:flex-row"><AppButton variant="principal" @click="$emit('confirm')">{{ actionLabel }}</AppButton><AppButton variant="discret" @click="$emit('cancel')">Annuler</AppButton></div>
    </section>
</template>
