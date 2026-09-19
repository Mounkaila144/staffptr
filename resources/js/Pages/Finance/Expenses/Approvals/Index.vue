<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppButton from '../../../../Components/AppButton.vue';
import LoadingSkeleton from '../../../../Components/LoadingSkeleton.vue';
import StatusBadge from '../../../../Components/StatusBadge.vue';
import AppLayout from '../../../../Layouts/AppLayout.vue';

const props = defineProps({
    expenses: { type: Array, required: true },
    readiness: { type: Object, required: true },
    view: { type: String, default: 'en-attente' },
    focusedExpenseId: { type: Number, default: null },
    success: { type: String, default: null },
});

const approvalForm = useForm({ return_to: props.view === 'decision' ? 'decision' : 'index' });
const refusalForm = useForm({ reason: '', return_to: props.view === 'decision' ? 'decision' : 'index' });
const refusalExpenseId = ref(null);
const previewExpenseId = ref(null);
const navigationPending = ref(false);

const hasExpenses = computed(() => props.expenses.length > 0);
const isHandledView = computed(() => props.view === 'traitees');
const isDirectDecision = computed(() => props.view === 'decision');
const title = computed(() => isHandledView.value ? 'Dépenses traitées' : (isDirectDecision.value ? 'Décider de la dépense' : 'Dépenses à approuver'));

const removeStartListener = router.on('start', () => { navigationPending.value = true; });
const removeFinishListener = router.on('finish', () => { navigationPending.value = false; });
onBeforeUnmount(() => {
    removeStartListener();
    removeFinishListener();
});

function approve(expense) {
    approvalForm.return_to = isDirectDecision.value ? 'decision' : 'index';
    approvalForm.patch(`/depenses/${expense.id}/approuver`, {
        preserveScroll: true,
        only: ['expenses', 'readiness', 'view', 'focusedExpenseId', 'success'],
    });
}

function openRefusal(expense) {
    refusalForm.clearErrors();
    refusalForm.reason = '';
    refusalExpenseId.value = expense.id;
}

function closeRefusal() {
    refusalExpenseId.value = null;
    refusalForm.reset();
    refusalForm.clearErrors();
}

function refuse(expense) {
    refusalForm.return_to = isDirectDecision.value ? 'decision' : 'index';
    refusalForm.patch(`/depenses/${expense.id}/refuser`, {
        preserveScroll: true,
        only: ['expenses', 'readiness', 'view', 'focusedExpenseId', 'success'],
        onSuccess: closeRefusal,
    });
}

function toggleAttachment(event, expense) {
    previewExpenseId.value = event.currentTarget.open ? expense.id : null;
}
</script>

<template>
    <Head :title="title" />

    <AppLayout
        :title="title"
        :back-label="isDirectDecision ? 'Retour aux notifications' : 'Retour à l’accueil'"
        :back-href="isDirectDecision ? '/notifications' : '/'"
        active-navigation="approvals"
    >
        <div class="grid min-w-0 gap-6">
            <header class="grid min-w-0 gap-2">
                <h1 class="break-words text-page-title">{{ title }}</h1>
                <p class="max-w-2xl text-ink-secondary">
                    {{ isHandledView ? 'Retrouvez ici les dépenses pour lesquelles vous avez déjà décidé.' : 'Chaque dépense exige les décisions de deux comptes de direction distincts, quel que soit son montant.' }}
                </p>
                <nav v-if="!isDirectDecision" class="flex min-w-0 flex-wrap gap-3" aria-label="Vues des approbations">
                    <Link href="/depenses/approbations" class="touch-target inline-flex items-center font-semibold text-primary underline-offset-4 hover:underline" :aria-current="!isHandledView ? 'page' : undefined">En attente</Link>
                    <Link href="/depenses/approbations?vue=traitees" class="touch-target inline-flex items-center font-semibold text-primary underline-offset-4 hover:underline" :aria-current="isHandledView ? 'page' : undefined">Traitées</Link>
                </nav>
            </header>

            <p v-if="success" class="rounded-xl border-2 border-success bg-success-soft p-4 font-semibold text-success" role="status">
                ✓ {{ success }}
            </p>

            <section
                v-if="!readiness.approval_available"
                class="grid min-w-0 gap-2 rounded-xl border-2 border-warning bg-warning-soft p-4"
                role="status"
                aria-labelledby="approval-unavailable-title"
            >
                <h2 id="approval-unavailable-title" class="font-semibold">Approbation indisponible</h2>
                <p>{{ readiness.message }}</p>
                <p class="text-sm text-ink-secondary">
                    Comptes approbateurs configurés : {{ readiness.approval_account_count }} sur 2.
                </p>
            </section>

            <p
                v-if="approvalForm.errors.approval"
                class="rounded-xl border-2 border-danger bg-danger-soft p-4 font-semibold text-danger"
                role="alert"
            >
                ⚠ {{ approvalForm.errors.approval }}
            </p>

            <LoadingSkeleton v-if="navigationPending" shape="cards" />

            <section v-if="!hasExpenses" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-neutral-soft p-5" aria-labelledby="empty-approvals-title">
                <h2 id="empty-approvals-title" class="text-card-title">{{ isHandledView ? 'Aucune dépense traitée.' : 'Vous êtes à jour.' }}</h2>
                <p>{{ isHandledView ? 'Vos décisions apparaîtront ici après traitement.' : readiness.approval_available ? "Aucune dépense n'attend votre approbation. Les demandes apparaîtront ici dès qu'un membre en créera." : readiness.message }}</p>
                <Link v-if="!isHandledView" href="/depenses/approbations?vue=traitees" class="touch-target inline-flex w-fit items-center font-semibold text-primary underline-offset-4 hover:underline">
                    Voir les dépenses traitées
                </Link>
                <Link v-else href="/depenses/approbations" class="touch-target inline-flex w-fit items-center font-semibold text-primary underline-offset-4 hover:underline">
                    Voir les dépenses en attente
                </Link>
            </section>

            <ul v-else class="grid min-w-0 gap-4" aria-label="Dépenses et décisions enregistrées">
                <li
                    v-for="expense in expenses"
                    :key="expense.id"
                    class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4 sm:p-6"
                >
                    <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                        <div class="grid min-w-0 gap-1">
                            <h2 class="break-words text-card-title">{{ expense.reason }}</h2>
                            <p class="font-semibold text-primary">{{ expense.formatted_amount }}</p>
                        </div>
                        <StatusBadge :status="expense.state" />
                    </div>

                    <p class="rounded-lg border border-separator bg-page p-3 font-semibold">
                        {{ expense.approval_progress }}
                    </p>

                    <dl class="grid min-w-0 gap-3 text-sm sm:grid-cols-2">
                        <div class="grid min-w-0 gap-1">
                            <dt class="font-semibold text-ink-secondary">Demandeur</dt>
                            <dd class="break-words">{{ expense.requester.name }}</dd>
                        </div>
                        <div class="grid min-w-0 gap-1">
                            <dt class="font-semibold text-ink-secondary">Bénéficiaire</dt>
                            <dd class="break-words">{{ expense.beneficiary }}</dd>
                        </div>
                        <div class="grid min-w-0 gap-1">
                            <dt class="font-semibold text-ink-secondary">Catégorie</dt>
                            <dd class="break-words">{{ expense.category }}</dd>
                        </div>
                        <div class="grid min-w-0 gap-1">
                            <dt class="font-semibold text-ink-secondary">Demandée le</dt>
                            <dd>{{ expense.created_at }}</dd>
                        </div>
                    </dl>

                    <div class="grid min-w-0 gap-1 text-sm">
                        <p class="font-semibold text-ink-secondary">Résultat attendu</p>
                        <p class="whitespace-pre-wrap break-words">{{ expense.expected_result }}</p>
                    </div>

                    <details
                        v-if="expense.attachment"
                        class="grid min-w-0 rounded-xl border border-separator bg-page p-3"
                        @toggle="toggleAttachment($event, expense)"
                    >
                        <summary class="touch-target cursor-pointer font-semibold text-primary">Consulter le justificatif sur cet écran</summary>
                        <div v-if="previewExpenseId === expense.id" class="grid min-w-0 gap-3 pt-3">
                            <p class="break-words text-sm text-ink-secondary">{{ expense.attachment.name }}</p>
                            <img
                                v-if="expense.attachment.is_image"
                                :src="expense.attachment.thumbnail_url || expense.attachment.inline_url"
                                :alt="`Justificatif : ${expense.attachment.name}`"
                                class="max-h-80 w-full rounded-lg border border-separator object-contain"
                                loading="lazy"
                            />
                            <iframe
                                v-else
                                :src="expense.attachment.inline_url"
                                :title="`Justificatif : ${expense.attachment.name}`"
                                class="h-80 w-full rounded-lg border border-separator bg-surface"
                                loading="lazy"
                            />
                        </div>
                    </details>

                    <section v-if="expense.approvals.length > 0" class="grid min-w-0 gap-2" :aria-labelledby="`decisions-${expense.id}`">
                        <h3 :id="`decisions-${expense.id}`" class="font-semibold">Décisions enregistrées</h3>
                        <ul class="grid min-w-0 gap-2">
                            <li
                                v-for="decision in expense.approvals"
                                :key="`${decision.approver}-${decision.decided_at}`"
                                class="grid min-w-0 gap-1 rounded-lg border border-separator p-3 text-sm"
                            >
                                <p class="font-semibold">
                                    {{ decision.decision === 'approve' ? '✓ Approbation' : '× Refus' }} — {{ decision.approver }}
                                </p>
                                <p>{{ decision.decided_at }}</p>
                                <p v-if="decision.comment" class="break-words">Motif : {{ decision.comment }}</p>
                            </li>
                        </ul>
                    </section>

                    <!--
                        AC 9 : en niveau rouge, une dépense de catégorie non essentielle porte un
                        avertissement explicite. Il n'empêche rien — les boutons de décision restent
                        actifs juste en dessous. Le rouge peut bloquer une écriture, jamais une
                        personne, et il ne bloque ici aucune approbation (AC 12).
                    -->
                    <p
                        v-if="expense.alert_warning"
                        class="rounded-lg border-2 border-danger bg-danger-soft p-3 font-semibold"
                        role="status"
                        data-testid="expense-alert-warning"
                    >
                        <span aria-hidden="true">⚠ </span>{{ expense.alert_warning }}
                    </p>

                    <p
                        v-if="expense.is_requester"
                        class="rounded-lg border-2 border-warning bg-warning-soft p-3 font-semibold"
                        role="status"
                    >
                        {{ expense.requester_message }}
                    </p>

                    <p v-else-if="expense.has_decided" class="rounded-lg border border-separator p-3 font-semibold">
                        Votre décision est déjà enregistrée et ne peut pas compter une seconde fois.
                    </p>

                    <div v-if="expense.can_decide" class="grid min-w-0 gap-3 sm:flex sm:flex-wrap">
                        <AppButton
                            variant="principal"
                            :busy="approvalForm.processing"
                            busy-label="Approbation en cours"
                            @click="approve(expense)"
                        >
                            Approuver cette dépense
                        </AppButton>
                        <AppButton variant="destructeur" @click="openRefusal(expense)">
                            Refuser avec un motif
                        </AppButton>
                    </div>

                    <form
                        v-if="refusalExpenseId === expense.id"
                        class="grid min-w-0 gap-3 rounded-xl border-2 border-danger bg-danger-soft p-4"
                        @submit.prevent="refuse(expense)"
                    >
                        <label :for="`refusal-reason-${expense.id}`" class="font-semibold">
                            Motif du refus <span aria-hidden="true">✱</span><span class="sr-only"> obligatoire</span>
                        </label>
                        <textarea
                            :id="`refusal-reason-${expense.id}`"
                            v-model="refusalForm.reason"
                            name="reason"
                            required
                            maxlength="255"
                            rows="3"
                            class="min-h-24 min-w-0 rounded-lg border border-separator bg-surface p-3 text-base"
                            :aria-invalid="refusalForm.errors.reason ? 'true' : 'false'"
                            :aria-describedby="refusalForm.errors.reason ? `refusal-error-${expense.id}` : undefined"
                        />
                        <p
                            v-if="refusalForm.errors.reason"
                            :id="`refusal-error-${expense.id}`"
                            class="font-semibold text-danger"
                            role="alert"
                        >
                            ⚠ {{ refusalForm.errors.reason }}
                        </p>
                        <p v-if="refusalForm.errors.approval" class="font-semibold text-danger" role="alert">
                            ⚠ {{ refusalForm.errors.approval }}
                        </p>
                        <div class="grid min-w-0 gap-3 sm:flex sm:flex-wrap">
                            <AppButton
                                type="submit"
                                variant="destructeur"
                                :busy="refusalForm.processing"
                                busy-label="Refus en cours"
                            >
                                Confirmer le refus
                            </AppButton>
                            <AppButton variant="secondaire" @click="closeRefusal">Annuler</AppButton>
                        </div>
                    </form>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
