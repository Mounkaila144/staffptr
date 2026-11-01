<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AppModal from '../../../Components/AppModal.vue';
import DataTable from '../../../Components/DataTable.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    register: { type: Object, required: true },
    capitalContributions: { type: Array, required: true },
    idempotencyKey: { type: String, required: true },
    canContribute: { type: Boolean, required: true },
});

const contributionForm = useForm({ contribution_amount: '', purpose: '', idempotency_key: props.idempotencyKey });
const refusalForm = useForm({ refusal_reason: '' });
const contributing = ref(false);
// Refus métier sans champ de formulaire (apport déjà tranché, propre apport) : affiché en tête.
const page = usePage();
const decisionError = computed(() => page.props.errors?.capital_contribution ?? '');
const refusing = ref(null);
const awaitingMyDecision = computed(() => props.capitalContributions.filter((contribution) => contribution.can_decide));
const contributionColumns = [
    { key: 'contributor_name', label: 'Directeur', primary: true },
    { key: 'amount_label', label: 'Montant', align: 'right' },
    { key: 'purpose', label: 'Destination' },
    { key: 'declared_on', label: 'Déclaré le' },
    { key: 'state_label', label: 'État' },
    { key: 'actions', label: 'Décision', align: 'right' },
];
const stateBadges = { en_attente: 'en attente', approuve: 'approuvee', refuse: 'refusee' };
function formatDate(iso) {
    const [year, month, day] = String(iso).split('-');

    return day ? `${day}/${month}/${year}` : iso;
}

function submitContribution() {
    // La clé d’idempotence est renouvelée par le serveur à chaque affichage : la reprendre après un
    // succès évite qu’un second apport, saisi sans recharger la page, soit pris pour un doublon.
    contributionForm.post('/finances/parts-de-contribution/apports', {
        preserveScroll: true,
        onSuccess: () => {
            contributionForm.reset();
            contributionForm.idempotency_key = props.idempotencyKey;
            contributing.value = false;
        },
    });
}

function approve(contribution) {
    useForm({}).patch(`/finances/parts-de-contribution/apports/${contribution.id}/approuver`, { preserveScroll: true });
}

function refuse(contribution) {
    refusalForm.patch(`/finances/parts-de-contribution/apports/${contribution.id}/refuser`, { preserveScroll: true, onSuccess: () => { refusing.value = null; refusalForm.reset(); } });
}

const palette = [
    { fill: 'fill-primary', dot: 'bg-primary' },
    { fill: 'fill-success', dot: 'bg-success' },
    { fill: 'fill-warning', dot: 'bg-warning' },
    { fill: 'fill-ink-secondary', dot: 'bg-ink-secondary' },
];
const totalSharesLabel = computed(() => new Intl.NumberFormat('fr-FR').format(props.register.total_shares));
const segments = computed(() => {
    let offset = 0;

    return props.register.directors.map((director, index) => {
        const segment = { ...director, ...palette[index % palette.length], offset, width: director.percentage_basis_points };
        offset += director.percentage_basis_points;

        return segment;
    });
});
const summaryLabel = computed(() => segments.value.map((segment) => `${segment.name} ${segment.percentage_label}`).join(', '));

// La trajectoire compte des dizaines de points dès la deuxième année ; n'en montrer que les
// changements évite une liste illisible tout en laissant la dilution se constater.
// Plusieurs lignes le même jour ne laissent que le pourcentage de fin de journée.
function dilutionSteps(timeline) {
    const endOfDay = timeline.filter((point, index) => index === timeline.length - 1 || point.occurred_on !== timeline[index + 1].occurred_on);

    return endOfDay.filter((point, index) => index === 0 || point.percentage_label !== endOfDay[index - 1].percentage_label);
}
</script>

<template>
    <AppLayout title="Parts de contribution" active-navigation="finance">
        <div class="grid min-w-0 gap-8">
            <PageHeader title="Registre des parts de contribution" description="Ce que chaque directeur a fait entrer dans l’entreprise, converti en parts permanentes. Les 10 % d’apporteur et les 30 % d’exécutant n’y figurent pas : ils ont déjà rémunéré leur bénéficiaire.">
                <template #actions>
                    <AppButton v-if="canContribute" variant="principal" @click="contributing = true">Enregistrer un apport</AppButton>
                </template>
            </PageHeader>

            <p v-if="decisionError" class="rounded-xl border border-danger/40 bg-danger-soft p-4 font-semibold text-danger" role="alert">⚠ {{ decisionError }}</p>

            <a v-if="awaitingMyDecision.length" href="#apports" class="flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-xl border border-warning/40 bg-warning-soft p-4 text-warning">
                <span class="font-semibold">⏳ {{ awaitingMyDecision.length }} apport{{ awaitingMyDecision.length > 1 ? 's attendent' : ' attend' }} votre accord</span>
                <span class="text-sm font-semibold underline underline-offset-4">Voir et décider</span>
            </a>

            <!--
                Synthèse : dans l'ordre du registre, jamais triée par taille — c'est une répartition
                de propriété, pas un classement. Chaque segment porte aussi son nom et son chiffre,
                la couleur n'est jamais la seule information.
            -->
            <section v-if="register.total_shares > 0" class="grid min-w-0 gap-5 rounded-xl border border-separator bg-surface p-5" aria-labelledby="register-summary-title">
                <div class="flex min-w-0 flex-wrap items-baseline justify-between gap-2">
                    <h2 id="register-summary-title" class="text-section-title">Répartition actuelle</h2>
                    <p class="text-sm text-ink-muted">{{ totalSharesLabel }} parts émises au total</p>
                </div>
                <svg class="h-3 w-full overflow-hidden rounded-full" viewBox="0 0 10000 12" preserveAspectRatio="none" role="img" :aria-label="summaryLabel">
                    <rect width="10000" height="12" class="fill-neutral-soft" />
                    <rect
                        v-for="segment in segments"
                        :key="segment.id"
                        :x="segment.offset"
                        :width="segment.width"
                        height="12"
                        :class="segment.fill"
                    />
                </svg>
                <ul class="grid min-w-0 gap-3 sm:grid-cols-2">
                    <li v-for="segment in segments" :key="segment.id" class="grid min-w-0 gap-1 rounded-lg border border-separator p-4">
                        <p class="flex min-w-0 items-center gap-2 text-sm font-medium text-ink-secondary">
                            <span class="size-2.5 shrink-0 rounded-full" :class="segment.dot" aria-hidden="true" />
                            <span class="truncate">{{ segment.name }}</span>
                        </p>
                        <p class="text-amount text-ink">{{ segment.percentage_label }}</p>
                        <p class="text-sm text-ink-secondary">{{ segment.shares_label }} parts</p>
                        <dl class="mt-2 grid gap-1 border-t border-separator pt-2 text-sm">
                            <div class="flex justify-between gap-2"><dt class="text-ink-muted">Argent apporté</dt><dd class="font-medium">{{ segment.contributed_amount_label }}</dd></div>
                            <div class="flex justify-between gap-2"><dt class="text-ink-muted">Encaissé grâce à lui</dt><dd class="font-medium">{{ segment.brought_amount_label }}</dd></div>
                        </dl>
                    </li>
                </ul>
            </section>

            <details class="rounded-xl border border-separator bg-surface p-4 text-sm">
                <summary class="cursor-pointer font-semibold text-primary">Comment les parts sont calculées</summary>
                <p class="mt-2 text-ink-secondary">{{ register.method }}</p>
            </details>

            <EmptyState
                v-if="register.directors.length === 0"
                title="Aucun directeur au registre."
                reason="Les parts apparaîtront dès qu’un encaissement sera attribué à un directeur apporteur, ou qu’un apport d’argent sera approuvé."
            />

            <section v-for="director in register.directors" :key="director.id" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4">
                <div class="flex min-w-0 flex-wrap items-baseline justify-between gap-3">
                    <h2 class="break-words text-section-title">{{ director.name }}</h2>
                    <p class="text-card-title">{{ director.percentage_label }} · {{ director.shares_label }} parts</p>
                </div>

                <EmptyState
                    v-if="director.entries.length === 0"
                    title="Aucune part à son nom pour l’instant."
                    reason="Un encaissement sans apporteur, ou dont l’apporteur n’est pas directeur, n’émet aucune part."
                />

                <template v-else>
                    <ul class="grid min-w-0 gap-3">
                        <li v-for="entry in director.entries" :key="entry.id" class="grid min-w-0 gap-2 rounded-lg border border-separator p-3">
                            <div class="flex min-w-0 flex-wrap justify-between gap-2">
                                <div class="min-w-0">
                                    <strong class="break-words">{{ entry.source_label }}</strong>
                                    <p class="text-sm text-ink-secondary">
                                        <template v-if="entry.origin_label !== entry.source_label">{{ entry.origin_label }} · </template>{{ entry.entry_type_label }} du {{ formatDate(entry.occurred_on) }}
                                        <template v-if="entry.receipt_number"> · reçu {{ entry.receipt_number }}</template>
                                    </p>
                                </div>
                                <span class="whitespace-nowrap font-bold">{{ entry.issued_shares_label }} parts</span>
                            </div>
                            <dl class="grid gap-3 sm:grid-cols-3">
                                <div><dt class="text-sm text-ink-secondary">Montant</dt><dd class="font-semibold">{{ entry.base_amount_label }}</dd></div>
                                <div><dt class="text-sm text-ink-secondary">Millésime</dt><dd class="font-semibold">{{ entry.vintage_label }}</dd></div>
                                <div><dt class="text-sm text-ink-secondary">Coefficient</dt><dd class="font-semibold">{{ entry.coefficient_label }}</dd></div>
                            </dl>
                            <p class="text-sm text-ink-secondary">{{ entry.reason }}</p>
                        </li>
                    </ul>

                    <div class="grid gap-2 rounded-lg bg-neutral-soft p-3">
                        <h3 class="font-semibold">Évolution de son pourcentage</h3>
                        <ol class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                            <li v-for="(point, index) in dilutionSteps(director.timeline)" :key="`${director.id}-${index}`">
                                {{ formatDate(point.occurred_on) }} : {{ point.percentage_label }}
                            </li>
                        </ol>
                        <p class="text-sm text-ink-secondary">Une part acquise ne décroît jamais ; seul le pourcentage se dilue quand d’autres contribuent.</p>
                    </div>
                </template>
            </section>

            <section class="grid min-w-0 gap-4">
                <h2 id="apports" class="scroll-mt-24 text-section-title">Apports d’argent personnel</h2>
                <p class="text-ink-secondary">Un apport exige l’accord des deux directeurs et reste définitif : aucun remboursement n’existe.</p>

                <AppModal v-if="canContribute" v-model:open="contributing" title="Enregistrer un apport" description="L’apport attend l’accord du second directeur ; aucune part n’est émise avant.">
                    <form id="contribution-form" class="grid gap-4" @submit.prevent="submitContribution">
                        <FormField id="contribution-amount" v-model="contributionForm.contribution_amount" variant="money" label="Montant versé (francs CFA)" required :error="contributionForm.errors.contribution_amount" />
                        <FormField id="contribution-purpose" v-model="contributionForm.purpose" label="À quoi cet argent est destiné" required :error="contributionForm.errors.purpose" />
                    </form>
                    <template #footer>
                        <AppButton variant="secondaire" @click="contributing = false">Annuler</AppButton>
                        <AppButton type="submit" form="contribution-form" variant="principal" :busy="contributionForm.processing">Soumettre pour accord</AppButton>
                    </template>
                </AppModal>

                <EmptyState
                    v-if="capitalContributions.length === 0"
                    title="Aucun apport enregistré."
                    reason="Un apport d’argent personnel n’est pas obligatoire : les parts naissent aussi des encaissements."
                />
                <DataTable v-else :columns="contributionColumns" :rows="capitalContributions" caption="Apports d’argent personnel">
                    <template #cell-amount_label="{ row }"><span class="font-semibold text-ink">{{ row.amount_label }}</span></template>
                    <template #cell-purpose="{ row }"><span class="grid gap-0.5"><span>{{ row.purpose }}</span><span v-if="row.refusal_reason" class="text-xs text-danger">Motif du refus : {{ row.refusal_reason }}</span></span></template>
                    <template #cell-declared_on="{ row }"><span class="grid gap-0.5"><span>{{ formatDate(row.declared_on) }}</span><span v-if="row.decided_by" class="text-xs text-ink-muted">tranché par {{ row.decided_by }}</span></span></template>
                    <template #cell-state_label="{ row }"><StatusBadge :status="stateBadges[row.state] ?? row.state_label" /></template>
                    <template #cell-actions="{ row }">
                        <span v-if="row.can_decide" class="inline-flex flex-wrap justify-end gap-2">
                            <AppButton variant="principal" @click="approve(row)">Approuver</AppButton>
                            <AppButton variant="secondaire" @click="refusing = row">Refuser</AppButton>
                        </span>
                    </template>
                </DataTable>

                <AppModal :open="Boolean(refusing)" :title="`Refuser l’apport de ${refusing?.contributor_name ?? ''}`" description="Le refus est définitif et son motif est consigné." @update:open="(open) => !open && (refusing = null)">
                    <form id="refusal-form" class="grid gap-4" @submit.prevent="refuse(refusing)">
                        <FormField id="refusal-reason" v-model="refusalForm.refusal_reason" label="Motif du refus" required :error="refusalForm.errors.refusal_reason" />
                    </form>
                    <template #footer>
                        <AppButton variant="secondaire" @click="refusing = null">Retour</AppButton>
                        <AppButton type="submit" form="refusal-form" variant="danger" :busy="refusalForm.processing">Refuser l’apport</AppButton>
                    </template>
                </AppModal>
            </section>
        </div>
    </AppLayout>
</template>
