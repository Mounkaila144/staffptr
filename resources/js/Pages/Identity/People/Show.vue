<script setup>
import { nextTick, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    profile: { type: Object, required: true },
    manager: { type: Object, default: null },
    subordinates: { type: Array, required: true },
    canEdit: { type: Boolean, required: true },
    departments: { type: Array, required: true },
    jobFunctions: { type: Array, required: true },
    managerOptions: { type: Array, required: true },
    relationTypes: { type: Array, required: true },
});

const fullNameField = ref(null);
const form = useForm({
    full_name: props.profile.full_name,
    phone: props.profile.phone.replace(/^\+227/, ''),
    department_id: props.profile.department_id ?? '',
    job_function_id: props.profile.job_function_id ?? '',
    manager_id: props.profile.manager_id ?? '',
    relation_type: props.profile.relation_type,
    contract_start_date: props.profile.contract_start_date ?? '',
    contract_end_date: props.profile.contract_end_date ?? '',
    photo: null,
});

function selectPhoto(event) {
    form.photo = event.target.files?.[0] ?? null;
}

function focusFirstError(errors) {
    nextTick(() => {
        const field = Object.keys(errors)[0];
        const element = field ? document.querySelector(`[name="${field}"]`) : null;
        element?.focus?.();
        if (!element) fullNameField.value?.focus?.();
    });
}

function updateProfile() {
    form.transform((data) => ({ ...data, _method: 'patch' })).post(`/personnes/${props.profile.person_id}`, {
        forceFormData: true,
        preserveScroll: true,
        onError: focusFirstError,
    });
}
</script>

<template>
    <AppLayout :title="profile.full_name" back-label="Accueil" active-navigation="team">
        <div class="grid min-w-0 gap-8">
            <header class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4 sm:grid-cols-[auto_minmax(0,1fr)] sm:items-center">
                <img v-if="profile.photo_url" :src="profile.photo_url" :alt="`Photo de ${profile.full_name}`" class="h-24 w-24 rounded-full border border-separator object-cover">
                <div v-else class="grid h-24 w-24 place-items-center rounded-full border border-separator bg-neutral-soft text-3xl text-neutral" aria-hidden="true">◇</div>
                <div class="grid min-w-0 gap-2">
                    <h1 class="break-words text-page-title">{{ profile.full_name }}</h1>
                    <p class="break-all text-ink-secondary">{{ profile.phone }}</p>
                    <div class="flex flex-wrap gap-2">
                        <StatusBadge :status="profile.state" />
                        <span class="inline-flex min-h-7 items-center rounded-full border border-separator px-2.5 py-1 text-sm font-semibold">{{ profile.operational_status_label }}</span>
                    </div>
                </div>
                <nav class="flex flex-wrap gap-3 sm:col-span-2" aria-label="Sections du dossier">
                    <Link :href="`/personnes/${profile.person_id}/documents`" class="touch-target inline-flex w-fit items-center justify-center rounded-lg border border-separator px-4 font-semibold text-action hover:bg-action-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action">
                        Voir les documents
                    </Link>
                    <Link :href="`/personnes/${profile.person_id}/historique`" class="touch-target inline-flex w-fit items-center justify-center rounded-lg border border-separator px-4 font-semibold text-action hover:bg-action-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-action">
                        Voir l’historique
                    </Link>
                </nav>
            </header>

            <section class="grid min-w-0 gap-4" aria-labelledby="profile-details-title">
                <h2 id="profile-details-title" class="text-section-title">Situation actuelle</h2>
                <dl class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4 sm:grid-cols-2">
                    <div class="grid min-w-0 gap-1"><dt class="text-sm font-semibold text-ink-secondary">Service</dt><dd class="break-words">{{ profile.department_name ?? 'Non renseigné' }}</dd></div>
                    <div class="grid min-w-0 gap-1"><dt class="text-sm font-semibold text-ink-secondary">Fonction</dt><dd class="break-words">{{ profile.job_function_name ?? 'Non renseignée' }}</dd></div>
                    <div class="grid min-w-0 gap-1"><dt class="text-sm font-semibold text-ink-secondary">Type de relation</dt><dd>{{ profile.relation_type_label }}</dd></div>
                    <div class="grid min-w-0 gap-1"><dt class="text-sm font-semibold text-ink-secondary">Responsable direct</dt><dd>{{ manager?.name ?? 'Non renseigné' }}</dd></div>
                    <div class="grid min-w-0 gap-1"><dt class="text-sm font-semibold text-ink-secondary">Début du contrat ou stage</dt><dd>{{ profile.contract_start_date ?? 'Non renseigné' }}</dd></div>
                    <div class="grid min-w-0 gap-1"><dt class="text-sm font-semibold text-ink-secondary">Fin du contrat ou stage</dt><dd>{{ profile.contract_end_date ?? 'Non renseignée' }}</dd></div>
                    <div class="grid min-w-0 gap-2 sm:col-span-2">
                        <dt class="text-sm font-semibold text-ink-secondary">Rôles</dt>
                        <dd class="flex flex-wrap gap-2"><span v-for="role in profile.roles" :key="role.value" class="rounded-full border border-separator px-3 py-1 text-sm font-semibold">{{ role.label }}</span></dd>
                    </div>
                </dl>
            </section>

            <section class="grid min-w-0 gap-4" aria-labelledby="team-title">
                <h2 id="team-title" class="text-section-title">Équipe directe</h2>
                <EmptyState v-if="subordinates.length === 0" title="Aucun subordonné direct." reason="Cette situation est normale lorsqu’aucune personne active ne dépend directement de cette fiche." />
                <div v-else class="grid min-w-0 gap-3 sm:grid-cols-2">
                    <article v-for="subordinate in subordinates" :key="subordinate.id" class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4">
                        <p class="break-words font-semibold">{{ subordinate.name }}</p>
                        <p class="break-words text-sm text-ink-secondary">{{ subordinate.job_function ?? 'Fonction non renseignée' }} · {{ subordinate.department ?? 'Service non renseigné' }}</p>
                    </article>
                </div>
            </section>

            <section v-if="canEdit" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" aria-labelledby="edit-profile-title">
                <div class="grid gap-1">
                    <h2 id="edit-profile-title" class="text-section-title">Modifier la fiche</h2>
                    <p class="text-sm text-ink-secondary">Les rôles restent gérés depuis l’écran Comptes et rôles.</p>
                </div>
                <form class="grid min-w-0 gap-4" enctype="multipart/form-data" @submit.prevent="updateProfile">
                    <FormField ref="fullNameField" id="full_name" v-model="form.full_name" label="Nom complet" :error="form.errors.full_name" required />
                    <FormField id="phone" v-model="form.phone" label="Téléphone" variant="phone" autocomplete="tel" :error="form.errors.phone" required />

                    <label class="grid min-w-0 gap-2 font-semibold" for="department_id">Service
                        <select id="department_id" v-model="form.department_id" name="department_id" class="touch-target min-w-0 max-w-full rounded-lg border border-separator bg-surface px-3 font-normal">
                            <option value="">Non renseigné</option>
                            <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                        </select>
                        <span v-if="form.errors.department_id" class="text-sm text-danger">⚠ {{ form.errors.department_id }}</span>
                    </label>

                    <label class="grid min-w-0 gap-2 font-semibold" for="job_function_id">Fonction
                        <select id="job_function_id" v-model="form.job_function_id" name="job_function_id" class="touch-target min-w-0 max-w-full rounded-lg border border-separator bg-surface px-3 font-normal">
                            <option value="">Non renseignée</option>
                            <option v-for="jobFunction in jobFunctions" :key="jobFunction.id" :value="jobFunction.id">{{ jobFunction.name }}</option>
                        </select>
                        <span v-if="form.errors.job_function_id" class="text-sm text-danger">⚠ {{ form.errors.job_function_id }}</span>
                    </label>

                    <label class="grid min-w-0 gap-2 font-semibold" for="manager_id">Responsable direct
                        <select id="manager_id" v-model="form.manager_id" name="manager_id" class="touch-target min-w-0 max-w-full rounded-lg border border-separator bg-surface px-3 font-normal">
                            <option value="">Aucun responsable</option>
                            <option v-for="option in managerOptions" :key="option.id" :value="option.id">{{ option.name }}</option>
                        </select>
                        <span v-if="form.errors.manager_id" class="text-sm text-danger">⚠ {{ form.errors.manager_id }}</span>
                    </label>

                    <label class="grid min-w-0 gap-2 font-semibold" for="relation_type">Type de relation
                        <select id="relation_type" v-model="form.relation_type" name="relation_type" required class="touch-target min-w-0 max-w-full rounded-lg border border-separator bg-surface px-3 font-normal">
                            <option v-for="type in relationTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                        <span v-if="form.errors.relation_type" class="text-sm text-danger">⚠ {{ form.errors.relation_type }}</span>
                    </label>

                    <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                        <label class="grid min-w-0 gap-2 font-semibold" for="contract_start_date">Date de début
                            <input id="contract_start_date" v-model="form.contract_start_date" name="contract_start_date" type="date" class="touch-target min-w-0 max-w-full rounded-lg border border-separator bg-surface px-3 font-normal">
                            <span v-if="form.errors.contract_start_date" class="text-sm text-danger">⚠ {{ form.errors.contract_start_date }}</span>
                        </label>
                        <label class="grid min-w-0 gap-2 font-semibold" for="contract_end_date">Date de fin
                            <input id="contract_end_date" v-model="form.contract_end_date" name="contract_end_date" type="date" class="touch-target min-w-0 max-w-full rounded-lg border border-separator bg-surface px-3 font-normal">
                            <span v-if="form.errors.contract_end_date" class="text-sm text-danger">⚠ {{ form.errors.contract_end_date }}</span>
                        </label>
                    </div>

                    <label class="grid min-w-0 gap-2 font-semibold" for="photo">Photo optionnelle
                        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="touch-target min-w-0 max-w-full rounded-lg border border-separator bg-surface px-3 py-2 font-normal" @change="selectPhoto">
                        <span class="text-sm font-normal text-ink-secondary">JPG, PNG ou WebP, 2 Mo maximum. Stockage public transitoire avant la story 3.5.</span>
                        <span v-if="form.errors.photo" class="text-sm text-danger">⚠ {{ form.errors.photo }}</span>
                    </label>

                    <AppButton type="submit" variant="principal" :busy="form.processing" busy-label="Enregistrement en cours">Enregistrer la fiche</AppButton>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
