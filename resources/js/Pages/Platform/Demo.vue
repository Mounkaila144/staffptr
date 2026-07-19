<script setup>
import { Head } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import ActionCard from '../../Components/ActionCard.vue';
import AppButton from '../../Components/AppButton.vue';
import EmptyState from '../../Components/EmptyState.vue';
import FormField from '../../Components/FormField.vue';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import OfflineBanner from '../../Components/OfflineBanner.vue';
import ProcessingQueue from '../../Components/ProcessingQueue.vue';
import SensitiveConfirmation from '../../Components/SensitiveConfirmation.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import { useDraft } from '../../Composables/useDraft';
import { useMoney } from '../../Composables/useMoney';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    auth: { type: Object, required: true },
});
const statuses = ['brouillon', 'en attente', 'validé', 'retourné', 'bloqué', 'en retard'];
const queueItems = [
    { id: 1, name: 'Aïcha Amadou', description: 'Rapport transmis il y a deux jours.' },
    { id: 2, name: 'Moussa Ibrahim', description: 'Rapport transmis hier.' },
    { id: 3, name: 'Fatima Ali', description: "Rapport transmis aujourd'hui." },
];
const form = reactive({ report: '', amount: '', phone: '' });
const reportField = ref(null);
const formError = ref('Décrivez en quelques mots ce qui a été réalisé.');
const offlineBanner = ref(null);
const loadingStarted = ref(false);
const { formatMoney } = useMoney();
const draft = useDraft('daily-report', 'anonymous', 'new', form);

function showFirstError() {
    formError.value = form.report ? '' : 'Décrivez en quelques mots ce qui a été réalisé.';
    if (formError.value) reportField.value?.focus();
}

function restoreDraft() {
    const restored = draft.restore();
    if (restored) Object.assign(form, restored);
}

function clearDraft() {
    draft.purge();
    Object.assign(form, { report: '', amount: '', phone: '' });
}
</script>

<template>
    <Head title="Socle d'interface" />
    <AppLayout title="Socle d'interface" :permissions="props.auth.permissions">
        <OfflineBanner ref="offlineBanner" />
        <div class="grid gap-10">
            <header class="grid gap-2">
                <p class="text-sm font-semibold uppercase tracking-wider text-primary">PTR Staff</p>
                <h1 class="text-screen-title">États transverses</h1>
                <p class="text-ink-secondary">Banc d'essai des composants communs, de leur lecture et de leur comportement.</p>
            </header>

            <section class="grid gap-4" aria-labelledby="status-title">
                <h2 id="status-title" class="text-section-title">Pastilles d'état</h2>
                <div class="flex flex-wrap gap-3"><StatusBadge v-for="status in statuses" :key="status" :status="status" /></div>
                <div class="flex flex-wrap gap-3"><StatusBadge status="en attente" variant="avec contexte" context="2 jours" /><StatusBadge status="validé" variant="compacte" /></div>
            </section>

            <section class="grid gap-4" aria-labelledby="button-title">
                <h2 id="button-title" class="text-section-title">Boutons et formulaire</h2>
                <div class="flex flex-col gap-3 md:flex-row"><AppButton variant="principal" @click="showFirstError">Envoyer mon rapport</AppButton><AppButton variant="secondaire">Enregistrer le brouillon</AppButton><AppButton variant="discret">Voir le détail</AppButton><AppButton variant="destructeur">Refuser la demande</AppButton></div>
                <AppButton busy busy-label="Envoi en cours">Envoyer mon rapport</AppButton>
                <form class="grid gap-4" @submit.prevent="showFirstError">
                    <FormField ref="reportField" v-model="form.report" id="daily-report" label="Travail réalisé" required :error="formError" @blur="draft.saveNow()" />
                    <FormField v-model="form.amount" id="amount" label="Montant" variant="money" @blur="draft.saveNow()" />
                    <p v-if="form.amount" class="text-right font-semibold">Aperçu : {{ formatMoney(form.amount) }}</p>
                    <FormField v-model="form.phone" id="phone" label="Téléphone" variant="phone" @blur="draft.saveNow()" />
                    <button type="submit" class="touch-target w-fit rounded-lg border border-primary px-4 font-semibold text-primary">Vérifier les champs</button>
                </form>
                <div class="grid gap-2 rounded-xl border border-separator bg-surface p-4" aria-live="polite">
                    <p v-if="draft.restored.value" class="font-semibold">{{ draft.restoredLabel.value }}</p>
                    <p v-if="draft.savedLabel.value" class="text-sm text-success">{{ draft.savedLabel.value }}</p>
                    <div class="flex flex-wrap gap-2"><AppButton variant="secondaire" @click="restoreDraft">Restaurer le brouillon</AppButton><AppButton variant="discret" @click="clearDraft">Repartir d'un formulaire vide</AppButton></div>
                </div>
            </section>

            <section class="grid gap-4" aria-labelledby="cards-title">
                <h2 id="cards-title" class="text-section-title">Cartes d'action</h2>
                <ActionCard title="Rapport du jour" description="Votre contribution est attendue avant 18 h." variant="urgente"><AppButton variant="secondaire">Rédiger mon rapport</AppButton></ActionCard>
                <ActionCard title="Objectif mensuel" description="Votre objectif reste visible comme confirmation." state="faite" />
                <ActionCard title="Revue hebdomadaire" description="Aucune action n'est requise cette semaine." state="non applicable" variant="compacte" />
                <ActionCard title="Carte masquée" description="Cette carte ne doit jamais être rendue." :permitted="false" />
            </section>

            <ProcessingQueue id="queue-error" label="File — décision échouée" :items="queueItems" state="erreur" sort-by="ancienneté" />
            <ProcessingQueue id="queue-progress" label="File — décision en cours" :items="queueItems.slice(0, 1)" state="en cours" sort-by="nom" />
            <ProcessingQueue id="queue-empty" label="File — tout est traité" state="vide" />
            <ProcessingQueue id="queue-loading" label="File — chargement" state="chargement" />

            <section class="grid gap-4" aria-labelledby="empty-title">
                <h2 id="empty-title" class="text-section-title">États vides</h2>
                <EmptyState tone="positive" title="Tous les rapports sont traités" reason="La file reprendra lorsqu'un nouveau rapport arrivera." />
                <EmptyState tone="neutral" title="Vous n'avez pas encore d'objectif pour juillet" reason="La période vient de commencer ; un objectif pourra être ajouté." action-label="Créer un objectif" />
                <EmptyState tone="filter" title="Aucun résultat pour ces filtres" reason="Les éléments existent peut-être avec des critères différents." />
            </section>

            <section class="grid gap-4" aria-labelledby="loading-title">
                <h2 id="loading-title" class="text-section-title">Chargement</h2>
                <LoadingSkeleton force-state="skeleton" />
                <LoadingSkeleton force-state="slow" shape="form" />
                <AppButton variant="secondaire" @click="loadingStarted = true">Démarrer un chargement chronométré</AppButton>
                <div data-testid="timed-loading"><LoadingSkeleton v-if="loadingStarted" /></div>
            </section>

            <section class="grid gap-4" aria-labelledby="offline-title">
                <h2 id="offline-title" class="text-section-title">Action sans connexion</h2>
                <p>Cette action reste disponible et explique clairement un échec réseau.</p>
                <AppButton variant="secondaire" data-testid="offline-action" @click="offlineBanner.reportFailedAction()">Envoyer maintenant</AppButton>
            </section>

            <SensitiveConfirmation amount="125 000 F CFA" amount-words="Cent vingt-cinq mille francs CFA" counterparty="Coopérative Azawak" reason="Acompte du contrat de juillet" consequence="Le paiement sera enregistré et transmis à la finance." action-label="Confirmer le paiement" />
        </div>
    </AppLayout>
</template>
