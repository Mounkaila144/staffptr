<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    variant: { type: String, default: 'standard' },
    context: { type: String, default: '' },
});

// Rouge et ambre ne mesurent que ΔE 1,6 en deutéranopie (seuil 8) : glyphe et libellé
// sont parfois les seuls canaux d'information et ne doivent jamais être retirés sur téléphone.
const states = {
    brouillon: ['✎', 'Brouillon', 'border-dashed border-neutral text-neutral'],
    'en attente': ['⏳', 'En attente', 'border-solid border-warning bg-warning-soft text-warning'],
    'validé': ['✓', 'Validé', 'border-solid border-success bg-success-soft text-success'],
    retourné: ['↩', 'À corriger', 'border-2 border-solid border-warning text-warning'],
    bloqué: ['⏸', 'Bloqué', 'border-solid border-neutral bg-neutral-soft text-neutral'],
    'en retard': ['⚠', 'En retard', 'border-solid border-danger bg-danger-soft text-danger'],
    invite: ['✉', 'Invité', 'border-solid border-warning bg-warning-soft text-warning'],
    actif: ['✓', 'Actif', 'border-solid border-success bg-success-soft text-success'],
    inactif: ['⏸', 'Désactivé', 'border-solid border-neutral bg-neutral-soft text-neutral'],
    suspendu: ['⏸', 'Suspendu', 'border-solid border-warning bg-warning-soft text-warning'],
    termine: ['■', 'Terminé', 'border-solid border-neutral bg-neutral-soft text-neutral'],
    archive: ['▣', 'Archivé', 'border-dashed border-neutral bg-neutral-soft text-neutral'],
    audit: ['✓', 'Consignée', 'border-solid border-primary bg-surface text-primary'],
    demandee: ['⏳', 'Demandée', 'border-solid border-warning bg-warning-soft text-warning'],
    approuvee: ['✓', 'Approuvée', 'border-solid border-success bg-success-soft text-success'],
    refusee: ['×', 'Refusée', 'border-2 border-solid border-danger bg-danger-soft text-danger'],
    annulee: ['⏸', 'Annulée', 'border-solid border-neutral bg-neutral-soft text-neutral'],
    // Accusé de lecture d'un document interne.
    accepte: ['✓', 'Accepté', 'border-solid border-success bg-success-soft text-success'],
    // Tâches.
    a_faire: ['○', 'À faire', 'border-solid border-separator-strong bg-surface text-ink-secondary'],
    en_cours: ['◔', 'En cours', 'border-solid border-primary/30 bg-primary-soft text-primary'],
    bloquee: ['⏸', 'Bloquée', 'border-solid border-warning bg-warning-soft text-warning'],
    terminee: ['✓', 'Terminée', 'border-solid border-success bg-success-soft text-success'],
    payee: ['✓', 'Payée', 'border-solid border-success bg-success-soft text-success'],
    impayee: ['⏳', 'Impayée', 'border-solid border-warning bg-warning-soft text-warning'],
    partiellement_payee: ['◐', 'Partiellement payée', 'border-solid border-warning bg-warning-soft text-warning'],
    // Encaissements, contrats et comptes financiers portent des valeurs d'état en anglais.
    validated: ['✓', 'Validé', 'border-solid border-success bg-success-soft text-success'],
    corrected: ['↻', 'Corrigé', 'border-solid border-neutral bg-neutral-soft text-neutral'],
    cancelled: ['⏸', 'Annulé', 'border-solid border-neutral bg-neutral-soft text-neutral'],
    active: ['✓', 'Actif', 'border-solid border-success bg-success-soft text-success'],
    inactive: ['⏸', 'Désactivé', 'border-solid border-neutral bg-neutral-soft text-neutral'],
    closed: ['■', 'Clôturé', 'border-solid border-neutral bg-neutral-soft text-neutral'],
};

// Un état inconnu s'affiche tel quel plutôt que sous un faux « Brouillon ».
const state = computed(() => states[props.status] ?? ['•', props.status.replaceAll('_', ' '), 'border-solid border-neutral text-neutral']);
</script>

<template>
    <!--
        `max-w-full` et `flex-wrap` sont indispensables à la variante « avec contexte » : le
        contexte est un nom technique long et sans espace — `daily_report_submitted` — que rien
        ne peut raccourcir. Sans eux, la pastille pousse la page au-delà de 320 px, et le
        débordement n'apparaît qu'avec les polices les plus larges, donc pas sur tous les postes.
    -->
    <span class="inline-flex min-h-7 max-w-full items-center gap-1 rounded-full border px-2.5 py-0.5 text-[0.8125rem] font-semibold" :class="[state[2], variant === 'avec contexte' ? 'flex-wrap' : 'whitespace-nowrap']" :data-status="status">
        <span aria-hidden="true">{{ state[0] }}</span>
        <span :class="variant === 'compacte' ? 'md:sr-only' : ''">{{ state[1] }}</span>
        <span v-if="variant === 'avec contexte' && context" class="min-w-0 break-all"> · {{ context }}</span>
    </span>
</template>
