<script setup>
import { nextTick, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    company: { type: Object, required: true },
    departments: { type: Array, required: true },
    jobFunctions: { type: Array, required: true },
});

const companyNameField = ref(null);
const departmentNameField = ref(null);
const functionNameField = ref(null);
const departmentCreation = ref(null);
const functionCreation = ref(null);
const busyAction = ref('');
const actionErrors = reactive({ department: '', function: '' });
const departmentDrafts = reactive(Object.fromEntries(props.departments.map((item) => [item.id, item.name])));
const functionDrafts = reactive(Object.fromEntries(props.jobFunctions.map((item) => [item.id, item.name])));

const companyForm = useForm({
    name: props.company.name,
    phone: props.company.phone ?? '',
    email: props.company.email ?? '',
    address: props.company.address ?? '',
    logo: null,
});
const departmentForm = useForm({ name: '' });
const functionForm = useForm({ name: '' });

function focusFirstError(errors, fallback) {
    nextTick(() => {
        const field = Object.keys(errors)[0];
        const element = field ? document.querySelector(`[name="${field}"]`) : null;
        element?.focus?.();
        if (!element) fallback?.value?.focus?.();
    });
}

function selectLogo(event) {
    companyForm.logo = event.target.files?.[0] ?? null;
}

function updateCompany() {
    companyForm.post('/organisation/entreprise', {
        forceFormData: true,
        preserveScroll: true,
        headers: { 'X-HTTP-Method-Override': 'PATCH' },
        onError: (errors) => focusFirstError(errors, companyNameField),
    });
}

function createDepartment() {
    departmentForm.post('/organisation/services', {
        preserveScroll: true,
        onSuccess: () => departmentForm.reset(),
        onError: (errors) => focusFirstError(errors, departmentNameField),
    });
}

function createFunction() {
    functionForm.post('/organisation/fonctions', {
        preserveScroll: true,
        onSuccess: () => functionForm.reset(),
        onError: (errors) => focusFirstError(errors, functionNameField),
    });
}

function renameDepartment(department) {
    busyAction.value = `department-name-${department.id}`;
    actionErrors.department = '';
    router.patch(`/organisation/services/${department.id}`, { name: departmentDrafts[department.id] }, {
        preserveScroll: true,
        onError: (errors) => {
            actionErrors.department = errors.name ?? 'Vérifiez le nom du service.';
            nextTick(() => document.getElementById(`department-${department.id}`)?.focus());
        },
        onFinish: () => { busyAction.value = ''; },
    });
}

function toggleDepartment(department) {
    const action = department.is_active ? 'desactiver' : 'reactiver';
    busyAction.value = `department-state-${department.id}`;
    actionErrors.department = '';
    router.patch(`/organisation/services/${department.id}/${action}`, {}, {
        preserveScroll: true,
        onError: (errors) => {
            actionErrors.department = errors.department ?? "L'état du service n'a pas pu être modifié.";
            nextTick(() => document.getElementById(`department-state-${department.id}`)?.focus());
        },
        onFinish: () => { busyAction.value = ''; },
    });
}

function renameFunction(jobFunction) {
    busyAction.value = `function-name-${jobFunction.id}`;
    actionErrors.function = '';
    router.patch(`/organisation/fonctions/${jobFunction.id}`, { name: functionDrafts[jobFunction.id] }, {
        preserveScroll: true,
        onError: (errors) => {
            actionErrors.function = errors.name ?? 'Vérifiez le nom de la fonction.';
            nextTick(() => document.getElementById(`function-${jobFunction.id}`)?.focus());
        },
        onFinish: () => { busyAction.value = ''; },
    });
}

function toggleFunction(jobFunction) {
    const action = jobFunction.is_active ? 'desactiver' : 'reactiver';
    busyAction.value = `function-state-${jobFunction.id}`;
    actionErrors.function = '';
    router.patch(`/organisation/fonctions/${jobFunction.id}/${action}`, {}, {
        preserveScroll: true,
        onError: (errors) => {
            actionErrors.function = errors.name ?? "L'état de la fonction n'a pas pu être modifié.";
            nextTick(() => document.getElementById(`function-state-${jobFunction.id}`)?.focus());
        },
        onFinish: () => { busyAction.value = ''; },
    });
}
</script>

<template>
    <AppLayout title="Organisation" back-label="Accueil" active-navigation="team">
        <div class="grid min-w-0 gap-8">
            <header class="grid gap-2">
                <h1 class="text-page-title">Entreprise, services et fonctions</h1>
                <p class="text-ink-secondary">Décrivez la structure commune afin que chacun trouve clairement sa place.</p>
            </header>

            <section class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4" aria-labelledby="company-title">
                <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                    <h2 id="company-title" class="text-section-title">Fiche entreprise</h2>
                    <img v-if="company.logo_url" :src="company.logo_url" :alt="`Logo de ${company.name}`" class="h-16 w-16 rounded-lg border border-separator object-contain">
                </div>
                <form class="grid min-w-0 gap-4" enctype="multipart/form-data" @submit.prevent="updateCompany">
                    <div class="grid min-w-0 gap-4 sm:grid-cols-2">
                        <FormField ref="companyNameField" id="name" v-model="companyForm.name" label="Nom de l’entreprise" :error="companyForm.errors.name" required />
                        <FormField id="phone" v-model="companyForm.phone" label="Téléphone" :error="companyForm.errors.phone" autocomplete="tel" />
                        <FormField id="email" v-model="companyForm.email" label="Adresse e-mail" :error="companyForm.errors.email" autocomplete="email" />
                        <FormField id="address" v-model="companyForm.address" label="Adresse" :error="companyForm.errors.address" autocomplete="street-address" />
                    </div>
                    <label class="grid min-w-0 gap-2 font-semibold" for="logo">
                        Logo optionnel
                        <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" class="touch-target min-w-0 max-w-full rounded-lg border border-separator bg-surface px-3 py-2 font-normal" @change="selectLogo">
                        <span class="text-sm font-normal text-ink-secondary">JPG, PNG ou WebP, 2 Mo maximum. Le logo est une ressource publique de marque.</span>
                        <span v-if="companyForm.errors.logo" id="logo-error" class="text-sm text-danger">⚠ {{ companyForm.errors.logo }}</span>
                    </label>
                    <AppButton type="submit" variant="principal" :busy="companyForm.processing" busy-label="Enregistrement en cours">Enregistrer la fiche</AppButton>
                </form>
            </section>

            <section class="grid min-w-0 gap-4" aria-labelledby="departments-title">
                <div class="grid gap-2">
                    <h2 id="departments-title" class="text-section-title">Services</h2>
                    <p class="text-ink-secondary">Un service avec des membres actifs doit d’abord être réaffecté avant sa désactivation.</p>
                </div>
                <form ref="departmentCreation" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end" @submit.prevent="createDepartment">
                    <FormField ref="departmentNameField" id="department-name" v-model="departmentForm.name" label="Nouveau service" :error="departmentForm.errors.name" required />
                    <AppButton type="submit" variant="principal" :busy="departmentForm.processing" busy-label="Création">Créer le service</AppButton>
                </form>
                <p v-if="actionErrors.department" role="alert" class="rounded-lg border border-danger bg-danger-soft p-3 font-semibold text-danger">⚠ {{ actionErrors.department }}</p>
                <EmptyState v-if="departments.length === 0" title="Aucun service pour le moment." reason="Créez le premier service pour commencer à décrire l’organisation." action-label="Créer un service" @action="departmentNameField?.focus()" />
                <div v-else class="grid min-w-0 gap-4">
                    <article v-for="department in departments" :key="department.id" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4">
                        <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                            <p class="break-words font-semibold">{{ department.name }}</p>
                            <StatusBadge :status="department.is_active ? 'actif' : 'inactif'" />
                        </div>
                        <p class="text-sm text-ink-secondary">{{ department.active_members_count }} membre(s) actif(s)</p>
                        <form class="grid min-w-0 gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end" @submit.prevent="renameDepartment(department)">
                            <FormField :id="`department-${department.id}`" v-model="departmentDrafts[department.id]" label="Nom du service" required />
                            <AppButton type="submit" variant="secondaire" :busy="busyAction === `department-name-${department.id}`" busy-label="Enregistrement">Renommer</AppButton>
                        </form>
                        <AppButton :id="`department-state-${department.id}`" :variant="department.is_active ? 'destructeur' : 'secondaire'" :busy="busyAction === `department-state-${department.id}`" busy-label="Mise à jour" @click="toggleDepartment(department)">{{ department.is_active ? 'Désactiver le service' : 'Réactiver le service' }}</AppButton>
                    </article>
                </div>
            </section>

            <section class="grid min-w-0 gap-4" aria-labelledby="functions-title">
                <h2 id="functions-title" class="text-section-title">Fonctions</h2>
                <form ref="functionCreation" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end" @submit.prevent="createFunction">
                    <FormField ref="functionNameField" id="function-name" v-model="functionForm.name" label="Nouvelle fonction" :error="functionForm.errors.name" required />
                    <AppButton type="submit" variant="principal" :busy="functionForm.processing" busy-label="Création">Créer la fonction</AppButton>
                </form>
                <p v-if="actionErrors.function" role="alert" class="rounded-lg border border-danger bg-danger-soft p-3 font-semibold text-danger">⚠ {{ actionErrors.function }}</p>
                <EmptyState v-if="jobFunctions.length === 0" title="Aucune fonction pour le moment." reason="Créez la première fonction pour identifier les contributions attendues." action-label="Créer une fonction" @action="functionNameField?.focus()" />
                <div v-else class="grid min-w-0 gap-4">
                    <article v-for="jobFunction in jobFunctions" :key="jobFunction.id" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4">
                        <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                            <p class="break-words font-semibold">{{ jobFunction.name }}</p>
                            <StatusBadge :status="jobFunction.is_active ? 'actif' : 'inactif'" />
                        </div>
                        <form class="grid min-w-0 gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end" @submit.prevent="renameFunction(jobFunction)">
                            <FormField :id="`function-${jobFunction.id}`" v-model="functionDrafts[jobFunction.id]" label="Nom de la fonction" required />
                            <AppButton type="submit" variant="secondaire" :busy="busyAction === `function-name-${jobFunction.id}`" busy-label="Enregistrement">Renommer</AppButton>
                        </form>
                        <AppButton :id="`function-state-${jobFunction.id}`" :variant="jobFunction.is_active ? 'destructeur' : 'secondaire'" :busy="busyAction === `function-state-${jobFunction.id}`" busy-label="Mise à jour" @click="toggleFunction(jobFunction)">{{ jobFunction.is_active ? 'Désactiver la fonction' : 'Réactiver la fonction' }}</AppButton>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
