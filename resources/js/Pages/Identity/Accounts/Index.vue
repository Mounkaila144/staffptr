<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import ActionCard from '../../../Components/ActionCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import SensitiveConfirmation from '../../../Components/SensitiveConfirmation.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    accounts: { type: Object, required: true },
    filters: { type: Object, required: true },
    filtersActive: { type: Boolean, required: true },
    roles: { type: Array, required: true },
    states: { type: Array, required: true },
    people: { type: Array, required: true },
    readiness: { type: Object, required: true },
    createdAccount: { type: Object, default: null },
    passwordResetChallenge: { type: Object, default: null },
    resetAccount: { type: Object, default: null },
});

const page = usePage();
const creationSection = ref(null);
const credentialVisible = ref(Boolean(props.createdAccount));
const resetCredentialVisible = ref(Boolean(props.resetAccount));
const resetConfirmation = ref(null);
const filterState = ref(props.filters.state ?? '');
const filterRole = ref(props.filters.role ?? '');
const roleDrafts = reactive({});
const archiveReasons = reactive({});
const confirmationCodes = reactive({});
const busyAction = ref('');
const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Africa/Niamey' });
const creation = useForm({
    person_mode: 'new',
    person_id: '',
    full_name: '',
    first_seen_at: today,
    phone: '',
    roles: [],
});

const errors = computed(() => page.props.errors ?? {});

watch(() => props.accounts.data, (accounts) => {
    for (const account of accounts) {
        roleDrafts[account.id] ??= { roles: [...account.roles], reason: '' };
        archiveReasons[account.id] ??= '';
        confirmationCodes[account.id] ??= '';
    }
}, { immediate: true });

watch(() => props.createdAccount, (createdAccount) => {
    credentialVisible.value = Boolean(createdAccount);
});

watch(() => props.resetAccount, (resetAccount) => {
    resetCredentialVisible.value = Boolean(resetAccount);
});

function focusCreation() {
    creationSection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    nextTick(() => document.getElementById('full_name')?.focus());
}

function submitCreation() {
    creation.post('/comptes', {
        preserveScroll: true,
        onSuccess: () => creation.reset(),
    });
}

function applyFilters() {
    router.get('/comptes', {
        state: filterState.value || undefined,
        role: filterRole.value || undefined,
    }, { preserveState: true, replace: true });
}

function resetFilters() {
    filterState.value = '';
    filterRole.value = '';
    router.get('/comptes', {}, { replace: true });
}

function syncRoles(account) {
    const key = `roles-${account.id}`;
    busyAction.value = key;
    router.patch(`/comptes/${account.id}/roles`, roleDrafts[account.id], {
        preserveScroll: true,
        onFinish: () => { busyAction.value = ''; },
    });
}

function archiveAccount(account) {
    const key = `archive-${account.id}`;
    busyAction.value = key;
    router.patch(`/comptes/${account.id}/archiver`, { reason: archiveReasons[account.id] }, {
        preserveScroll: true,
        onFinish: () => { busyAction.value = ''; },
    });
}

function askForReset(account) {
    resetConfirmation.value = account;
}

function initiateReset(account) {
    const key = `reset-initiate-${account.id}`;
    busyAction.value = key;
    resetConfirmation.value = null;
    router.post(`/comptes/${account.id}/reinitialisation/initier`, {}, {
        preserveScroll: true,
        onFinish: () => { busyAction.value = ''; },
    });
}

function confirmReset(account) {
    const key = `reset-confirm-${account.id}`;
    busyAction.value = key;
    router.post(`/comptes/${account.id}/reinitialisation/confirmer`, {
        confirmation_code: confirmationCodes[account.id],
    }, {
        preserveScroll: true,
        onFinish: () => { busyAction.value = ''; },
    });
}
</script>

<template>
    <Head title="Comptes et rôles" />
    <AppLayout title="Comptes et rôles" active-navigation="accounts">
        <div class="grid w-full min-w-0 max-w-full gap-8 overflow-x-clip">
            <header class="grid gap-2">
                <h1 class="text-screen-title">Administration des comptes</h1>
                <p class="text-ink-secondary">Créez les accès, attribuez les responsabilités et archivez un compte sans supprimer sa fiche personne.</p>
            </header>

            <section v-if="createdAccount && credentialVisible" class="grid gap-4 rounded-xl border-2 border-warning bg-warning-soft p-5" aria-live="polite">
                <div class="grid gap-2">
                    <h2 class="text-section-title">Mot de passe temporaire — affiché une seule fois</h2>
                    <p>Transmettez-le hors application à <strong>{{ createdAccount.person_name }}</strong>. Il ne pourra pas être récupéré après cette page.</p>
                </div>
                <dl class="grid min-w-0 gap-2 rounded-lg bg-surface p-4">
                    <div><dt class="font-semibold">Téléphone</dt><dd class="break-all">{{ createdAccount.phone }}</dd></div>
                    <div><dt class="font-semibold">Mot de passe temporaire</dt><dd class="break-all font-mono text-lg">{{ createdAccount.temporary_password }}</dd></div>
                </dl>
                <AppButton variant="secondaire" class="w-fit" @click="credentialVisible = false">J’ai conservé ces informations</AppButton>
            </section>

            <section v-if="resetAccount && resetCredentialVisible" class="grid gap-4 rounded-xl border-2 border-warning bg-warning-soft p-5" aria-live="polite">
                <div class="grid gap-2">
                    <h2 class="text-section-title">Nouveau mot de passe temporaire — affiché une seule fois</h2>
                    <p>Transmettez-le hors application à <strong>{{ resetAccount.person_name }}</strong>. Il n’est envoyé ni par WhatsApp ni par un autre canal applicatif.</p>
                </div>
                <dl class="grid min-w-0 gap-2 rounded-lg bg-surface p-4">
                    <div><dt class="font-semibold">Téléphone</dt><dd class="break-all">{{ resetAccount.phone }}</dd></div>
                    <div><dt class="font-semibold">Mot de passe temporaire</dt><dd class="break-all font-mono text-lg">{{ resetAccount.temporary_password }}</dd></div>
                </dl>
                <p class="text-sm text-ink-secondary">Il ne pourra pas être récupéré après cette réponse. S’il est perdu, effectuez une nouvelle réinitialisation.</p>
                <AppButton variant="secondaire" class="w-fit" @click="resetCredentialVisible = false">J’ai conservé le mot de passe</AppButton>
            </section>

            <EmptyState
                v-if="readiness.first_launch"
                title="Deux comptes direction sont nécessaires"
                :reason="readiness.message"
                action-label="Créer un compte direction"
                @action="focusCreation"
            />
            <ActionCard
                v-else-if="!readiness.approval_available"
                title="Approbation des dépenses indisponible"
                :description="readiness.message"
                variant="urgente"
            >
                <AppButton variant="secondaire" @click="focusCreation">Créer le compte direction manquant</AppButton>
            </ActionCard>

            <section ref="creationSection" class="grid min-w-0 max-w-full gap-5 rounded-xl border border-separator bg-surface p-4 sm:p-5" aria-labelledby="create-account-title">
                <div class="grid gap-1">
                    <h2 id="create-account-title" class="text-section-title">Créer un compte</h2>
                    <p class="text-sm text-ink-secondary">Le compte sera actif et demandera un nouveau mot de passe à sa première connexion.</p>
                </div>
                <form class="grid min-w-0 max-w-full gap-5" @submit.prevent="submitCreation">
                    <fieldset class="grid min-w-0 max-w-full gap-3">
                        <legend class="font-semibold">Fiche personne</legend>
                        <label class="touch-target flex items-center gap-3 rounded-lg border border-separator px-3">
                            <input v-model="creation.person_mode" type="radio" value="new"> Nouvelle personne
                        </label>
                        <label class="touch-target flex items-center gap-3 rounded-lg border border-separator px-3">
                            <input v-model="creation.person_mode" type="radio" value="existing"> Personne existante ou de retour
                        </label>
                    </fieldset>

                    <div v-if="creation.person_mode === 'new'" class="grid gap-4 sm:grid-cols-2">
                        <FormField id="full_name" v-model="creation.full_name" label="Nom complet" :error="creation.errors.full_name" required />
                        <label class="grid gap-2 text-field-label font-semibold" for="first_seen_at">Première date connue
                            <input id="first_seen_at" v-model="creation.first_seen_at" name="first_seen_at" type="date" required class="touch-target rounded-lg border border-separator bg-surface px-3 font-normal">
                            <span v-if="creation.errors.first_seen_at" class="text-sm text-danger">⚠ {{ creation.errors.first_seen_at }}</span>
                        </label>
                    </div>
                    <label v-else class="grid gap-2 font-semibold" for="person_id">Personne existante
                        <select id="person_id" v-model="creation.person_id" required class="touch-target w-full min-w-0 max-w-full rounded-lg border border-separator bg-surface px-3 font-normal">
                            <option value="" disabled>Choisir une fiche</option>
                            <option v-for="person in people" :key="person.id" :value="person.id">{{ person.name }} — {{ person.accounts_count }} compte(s)</option>
                        </select>
                        <span v-if="creation.errors.person_id" class="text-sm text-danger">⚠ {{ creation.errors.person_id }}</span>
                    </label>

                    <FormField id="phone" v-model="creation.phone" label="Numéro de téléphone" variant="phone" autocomplete="tel" :error="creation.errors.phone" required />

                    <fieldset class="grid min-w-0 max-w-full gap-2">
                        <legend class="font-semibold">Rôles initiaux</legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <label v-for="role in roles" :key="role.value" class="touch-target flex items-center gap-3 rounded-lg border border-separator px-3">
                                <input v-model="creation.roles" type="checkbox" :value="role.value"> {{ role.label }}
                            </label>
                        </div>
                        <p v-if="creation.errors.roles" class="text-sm font-semibold text-danger">⚠ {{ creation.errors.roles }}</p>
                    </fieldset>

                    <AppButton type="submit" variant="principal" :busy="creation.processing" busy-label="Création en cours">Créer et afficher le mot de passe</AppButton>
                </form>
            </section>

            <section class="grid min-w-0 max-w-full gap-4" aria-labelledby="accounts-title">
                <div class="grid gap-3">
                    <h2 id="accounts-title" class="text-section-title">Comptes</h2>
                    <form class="grid min-w-0 max-w-full gap-4 rounded-xl border border-separator bg-surface p-4" aria-label="Filtrer les comptes" @submit.prevent="applyFilters">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="grid gap-1 font-semibold">État
                                <select v-model="filterState" class="touch-target w-full min-w-0 max-w-full rounded-lg border border-separator bg-surface px-3 font-normal">
                                    <option value="">Tous les états</option>
                                    <option v-for="state in states" :key="state.value" :value="state.value">{{ state.label }}</option>
                                </select>
                            </label>
                            <label class="grid gap-1 font-semibold">Rôle
                                <select v-model="filterRole" class="touch-target w-full min-w-0 max-w-full rounded-lg border border-separator bg-surface px-3 font-normal">
                                    <option value="">Tous les rôles</option>
                                    <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                                </select>
                            </label>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <AppButton type="submit" variant="secondaire">Filtrer</AppButton>
                            <AppButton v-if="filtersActive" variant="discret" @click="resetFilters">Réinitialiser les filtres</AppButton>
                        </div>
                    </form>
                </div>

                <EmptyState
                    v-if="!accounts.data.length && filtersActive"
                    tone="filter"
                    title="Aucun compte ne correspond à ces filtres"
                    reason="Les comptes existent toujours. Modifiez ou réinitialisez les filtres pour les retrouver."
                    @action="resetFilters"
                />

                <div v-else class="grid min-w-0 gap-4">
                    <article v-for="account in accounts.data" :key="account.id" class="grid min-w-0 gap-5 rounded-xl border border-separator bg-surface p-4">
                        <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="break-words text-card-title">{{ account.person.name }} <span v-if="account.is_self" class="text-ink-secondary">(votre compte)</span></h3>
                                <p class="break-all text-sm text-ink-secondary">{{ account.phone }}</p>
                                <p class="text-sm text-ink-secondary">Fiche personne #{{ account.person.id }} · statut {{ account.person.operational_status }}</p>
                            </div>
                            <StatusBadge :status="account.state" />
                        </div>

                        <form class="grid min-w-0 max-w-full gap-3 border-t border-separator pt-4" @submit.prevent="syncRoles(account)">
                            <fieldset class="grid min-w-0 max-w-full gap-2">
                                <legend class="font-semibold">Rôles</legend>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label v-for="role in roles" :key="role.value" class="touch-target flex items-center gap-3 rounded-lg border border-separator px-3">
                                        <input v-model="roleDrafts[account.id].roles" type="checkbox" :value="role.value"> {{ role.label }}
                                    </label>
                                </div>
                            </fieldset>
                            <FormField :id="`role-reason-${account.id}`" v-model="roleDrafts[account.id].reason" label="Motif du changement de rôles" required />
                            <p v-if="errors.roles" class="text-sm font-semibold text-danger">⚠ {{ errors.roles }}</p>
                            <AppButton type="submit" variant="secondaire" :busy="busyAction === `roles-${account.id}`" busy-label="Mise à jour">Enregistrer les rôles</AppButton>
                        </form>

                        <section v-if="account.state !== 'archive'" class="grid min-w-0 max-w-full gap-3 border-t border-separator pt-4" :aria-labelledby="`reset-title-${account.id}`">
                            <div class="grid gap-1">
                                <h4 :id="`reset-title-${account.id}`" class="font-semibold">Réinitialiser le mot de passe <span class="sr-only">de {{ account.person.name }}</span></h4>
                                <p class="text-sm text-ink-secondary">Un code à 6 chiffres sera envoyé sur le WhatsApp enregistré de la cible. La cible doit vous lire ce code avant toute génération du mot de passe temporaire.</p>
                                <p class="text-sm font-semibold text-warning">Si WhatsApp est indisponible, l’opération reste bloquée sans contournement. Le mot de passe temporaire ne sera jamais envoyé par WhatsApp.</p>
                            </div>

                            <form v-if="passwordResetChallenge?.user_id === account.id" class="grid gap-3 rounded-xl border border-primary bg-primary-soft p-4" @submit.prevent="confirmReset(account)">
                                <p>Code envoyé à <strong>{{ passwordResetChallenge.person_name }}</strong>. Il expire dans {{ passwordResetChallenge.expires_in_minutes }} minutes.</p>
                                <FormField :id="`confirmation-code-${account.id}`" v-model="confirmationCodes[account.id]" label="Code de confirmation WhatsApp" variant="code" autocomplete="one-time-code" :error="errors.confirmation_code" required />
                                <AppButton type="submit" variant="principal" :busy="busyAction === `reset-confirm-${account.id}`" busy-label="Vérification">Confirmer le code et générer le mot de passe</AppButton>
                            </form>

                            <SensitiveConfirmation
                                v-else-if="resetConfirmation?.id === account.id"
                                amount="1 compte"
                                amount-words="Réinitialisation du mot de passe"
                                :counterparty="account.person.name"
                                reason="Demande vérifiée par possession du WhatsApp enregistré"
                                consequence="Toutes les sessions de la cible seront révoquées après validation du code. Son prochain accès imposera un changement de mot de passe."
                                action-label="Envoyer le code WhatsApp"
                                @confirm="initiateReset(account)"
                                @cancel="resetConfirmation = null"
                            />

                            <template v-else>
                                <p v-if="errors.password_reset" class="text-sm font-semibold text-danger">⚠ {{ errors.password_reset }}</p>
                                <AppButton variant="secondaire" :busy="busyAction === `reset-initiate-${account.id}`" busy-label="Envoi du code" @click="askForReset(account)">Commencer la vérification WhatsApp</AppButton>
                            </template>
                        </section>

                        <form v-if="account.state !== 'archive'" class="grid min-w-0 max-w-full gap-3 border-t border-separator pt-4" @submit.prevent="archiveAccount(account)">
                            <div class="grid gap-1">
                                <h4 class="font-semibold">Archiver le compte</h4>
                                <p class="text-sm text-ink-secondary">La fiche personne reste intacte et consultable. Le numéro pourra être réutilisé. Le statut opérationnel de la personne ne change pas.</p>
                            </div>
                            <FormField :id="`archive-reason-${account.id}`" v-model="archiveReasons[account.id]" label="Motif de l’archivage" required />
                            <p v-if="errors.reason" class="text-sm font-semibold text-danger">⚠ {{ errors.reason }}</p>
                            <AppButton type="submit" variant="destructeur" :busy="busyAction === `archive-${account.id}`" busy-label="Archivage">Archiver le compte</AppButton>
                        </form>
                    </article>
                </div>

                <nav v-if="accounts.prev_page_url || accounts.next_page_url" class="flex items-center justify-between gap-3" aria-label="Pagination des comptes">
                    <Link v-if="accounts.prev_page_url" :href="accounts.prev_page_url" class="touch-target inline-flex items-center rounded-lg px-3 font-semibold text-primary">Précédent</Link><span v-else />
                    <p class="text-sm text-ink-secondary">Page {{ accounts.current_page }} sur {{ accounts.last_page }}</p>
                    <Link v-if="accounts.next_page_url" :href="accounts.next_page_url" class="touch-target inline-flex items-center rounded-lg px-3 font-semibold text-primary">Suivant</Link><span v-else />
                </nav>
            </section>
        </div>
    </AppLayout>
</template>
