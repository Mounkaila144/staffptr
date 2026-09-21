<script setup>
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    register: { type: Object, required: true },
    capitalContributions: { type: Array, required: true },
    idempotencyKey: { type: String, required: true },
    canContribute: { type: Boolean, required: true },
});

const contributionForm = useForm({ contribution_amount: 0, purpose: '', idempotency_key: props.idempotencyKey });
const refusalForm = useForm({ refusal_reason: '' });

function submitContribution() {
    contributionForm.post('/finances/parts-de-contribution/apports', { preserveScroll: true });
}

function approve(contribution) {
    useForm({}).patch(`/finances/parts-de-contribution/apports/${contribution.id}/approuver`, { preserveScroll: true });
}

function refuse(contribution) {
    refusalForm.patch(`/finances/parts-de-contribution/apports/${contribution.id}/refuser`, { preserveScroll: true });
}

// La trajectoire compte des dizaines de points dès la deuxième année ; n'en montrer que les
// changements évite une liste illisible tout en laissant la dilution se constater.
function dilutionSteps(timeline) {
    return timeline.filter((point, index) => index === 0 || point.percentage_label !== timeline[index - 1].percentage_label);
}
</script>

<template>
    <AppLayout title="Parts de contribution" active-navigation="finance">
        <div class="grid min-w-0 gap-8">
            <header class="grid gap-2">
                <h1 class="break-words text-page-title">Registre des parts de contribution</h1>
                <p class="text-ink-secondary">
                    Ce que chaque directeur a fait entrer dans l’entreprise, converti en parts permanentes.
                    Les 10 % d’apporteur et les 30 % d’exécutant n’y figurent pas : ils ont déjà rémunéré leur bénéficiaire.
                </p>
                <p class="rounded-lg bg-neutral-soft p-3 text-sm"><strong>Méthode :</strong> {{ register.method }}</p>
            </header>

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
                                        {{ entry.origin_label }} · {{ entry.entry_type_label }} du {{ entry.occurred_on }}
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
                                {{ point.occurred_on }} : {{ point.percentage_label }}
                            </li>
                        </ol>
                        <p class="text-sm text-ink-secondary">Une part acquise ne décroît jamais ; seul le pourcentage se dilue quand d’autres contribuent.</p>
                    </div>
                </template>
            </section>

            <section class="grid min-w-0 gap-4">
                <h2 class="text-section-title">Apports d’argent personnel</h2>
                <p class="text-ink-secondary">Un apport exige l’accord des deux directeurs et reste définitif : aucun remboursement n’existe.</p>

                <form v-if="canContribute" class="grid gap-4 rounded-xl border border-separator bg-surface p-4" @submit.prevent="submitContribution">
                    <h3 class="font-semibold">Enregistrer un apport</h3>
                    <FormField id="contribution-amount" v-model="contributionForm.contribution_amount" type="number" min="1" label="Montant versé en XOF" required :error="contributionForm.errors.contribution_amount" />
                    <FormField id="contribution-purpose" v-model="contributionForm.purpose" label="À quoi cet argent est destiné" required :error="contributionForm.errors.purpose" />
                    <AppButton type="submit" variant="principal" :busy="contributionForm.processing">Soumettre à l’accord du second directeur</AppButton>
                </form>

                <EmptyState
                    v-if="capitalContributions.length === 0"
                    title="Aucun apport enregistré."
                    reason="Un apport d’argent personnel n’est pas obligatoire : les parts naissent aussi des encaissements."
                />
                <ul v-else class="grid min-w-0 gap-3">
                    <li v-for="contribution in capitalContributions" :key="contribution.id" class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4">
                        <div class="flex min-w-0 flex-wrap justify-between gap-2">
                            <strong class="break-words">{{ contribution.contributor_name }} · {{ contribution.amount_label }}</strong>
                            <span>{{ contribution.state_label }}</span>
                        </div>
                        <p class="text-sm">{{ contribution.purpose }}</p>
                        <p class="text-sm text-ink-secondary">Déclaré le {{ contribution.declared_on }}<template v-if="contribution.decided_by"> · tranché par {{ contribution.decided_by }}</template></p>
                        <p v-if="contribution.refusal_reason" class="text-sm"><strong>Motif du refus :</strong> {{ contribution.refusal_reason }}</p>
                        <div v-if="contribution.can_decide" class="grid gap-2">
                            <FormField :id="`refusal-${contribution.id}`" v-model="refusalForm.refusal_reason" label="Motif en cas de refus" :error="refusalForm.errors.refusal_reason" />
                            <div class="flex flex-wrap gap-2">
                                <AppButton variant="principal" @click="approve(contribution)">Approuver l’apport</AppButton>
                                <AppButton variant="secondaire" @click="refuse(contribution)">Refuser</AppButton>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
