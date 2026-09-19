<script setup>
import { nextTick, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({
    categories: { type: Array, required: true },
    activeCategories: { type: Array, required: true },
});

const creationOpen = ref(false);
const creationNameField = ref(null);
const editingId = ref(null);
const editNameField = ref(null);
const creationForm = useForm({ name: '', is_essential: false });
const editForm = useForm({ name: '', is_essential: false });

async function openCreation() {
    creationOpen.value = true;
    await nextTick();
    creationNameField.value?.focus();
}

function submitCreation() {
    creationForm.post('/categories-depense', {
        preserveScroll: true,
        onSuccess: () => { creationForm.reset(); creationOpen.value = false; },
        onError: () => nextTick(() => creationNameField.value?.focus()),
    });
}

async function startEditing(category) {
    editingId.value = category.id;
    editForm.name = category.name;
    editForm.is_essential = category.is_essential;
    editForm.clearErrors();
    await nextTick();
    editNameField.value?.focus();
}

function stopEditing() {
    editingId.value = null;
    editForm.clearErrors();
}

function submitEdit(category) {
    editForm.patch(`/categories-depense/${category.id}`, {
        preserveScroll: true,
        onSuccess: stopEditing,
        onError: () => nextTick(() => editNameField.value?.focus()),
    });
}

function changeActivity(category) {
    const action = category.is_active ? 'desactiver' : 'reactiver';
    router.patch(`/categories-depense/${category.id}/${action}`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Catégories de dépense" back-label="Paramètres" back-href="/parametres" active-navigation="settings">
        <div class="grid min-w-0 gap-8">
            <header class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                <div class="grid min-w-0 gap-2">
                    <h1 class="break-words text-page-title">Catégories de dépense</h1>
                    <p class="max-w-2xl text-ink-secondary">Adaptez les natures de dépense sans redéploiement. Une catégorie désactivée reste conservée dans l’historique.</p>
                    <p class="text-sm font-semibold text-primary">{{ activeCategories.length }} catégorie{{ activeCategories.length > 1 ? 's' : '' }} actuellement sélectionnable{{ activeCategories.length > 1 ? 's' : '' }}.</p>
                </div>
                <AppButton variant="principal" @click="openCreation">Créer une catégorie</AppButton>
            </header>

            <form v-if="creationOpen" class="grid min-w-0 gap-4 rounded-xl border border-primary bg-primary-soft p-4" @submit.prevent="submitCreation">
                <h2 class="text-section-title">Nouvelle catégorie</h2>
                <FormField ref="creationNameField" id="expense-category-name" v-model="creationForm.name" label="Nom" required :error="creationForm.errors.name" />
                <label class="touch-target flex min-w-0 items-center gap-3 rounded-lg border border-separator bg-surface px-3">
                    <input v-model="creationForm.is_essential" type="checkbox">
                    Dépense essentielle
                </label>
                <p v-if="creationForm.errors.is_essential" class="text-sm font-semibold text-danger">⚠ {{ creationForm.errors.is_essential }}</p>
                <div class="flex flex-wrap gap-2">
                    <AppButton type="submit" variant="principal" :busy="creationForm.processing" busy-label="Création en cours">Créer</AppButton>
                    <AppButton variant="secondaire" @click="creationOpen = false">Annuler</AppButton>
                </div>
            </form>

            <EmptyState
                v-if="categories.length === 0"
                title="Aucune catégorie de dépense."
                reason="Créez la première catégorie pour qu’elle soit immédiatement proposée dans les demandes."
                action-label="Créer une catégorie"
                @action="openCreation"
            />

            <ul v-else class="grid min-w-0 gap-3 sm:grid-cols-2">
                <li v-for="category in categories" :key="category.id" class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-4">
                    <form v-if="editingId === category.id" class="grid min-w-0 gap-4" @submit.prevent="submitEdit(category)">
                        <FormField ref="editNameField" :id="`expense-category-name-${category.id}`" v-model="editForm.name" label="Nom" required :error="editForm.errors.name" />
                        <label class="touch-target flex min-w-0 items-center gap-3 rounded-lg border border-separator px-3">
                            <input v-model="editForm.is_essential" type="checkbox">
                            Dépense essentielle
                        </label>
                        <p v-if="editForm.errors.is_essential" class="text-sm font-semibold text-danger">⚠ {{ editForm.errors.is_essential }}</p>
                        <div class="flex flex-wrap gap-2"><AppButton type="submit" variant="principal" :busy="editForm.processing">Enregistrer</AppButton><AppButton variant="secondaire" @click="stopEditing">Annuler</AppButton></div>
                    </form>
                    <template v-else>
                        <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                            <h2 class="min-w-0 break-words text-card-title">{{ category.name }}</h2>
                            <StatusBadge :status="category.is_active ? 'actif' : 'inactif'" />
                        </div>
                        <p class="font-semibold" :class="category.is_essential ? 'text-success' : 'text-ink-secondary'">
                            {{ category.is_essential ? 'Dépense essentielle' : 'Dépense non essentielle' }}
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <AppButton variant="secondaire" @click="startEditing(category)">Modifier</AppButton>
                            <AppButton :variant="category.is_active ? 'destructeur' : 'secondaire'" @click="changeActivity(category)">{{ category.is_active ? 'Désactiver' : 'Réactiver' }}</AppButton>
                        </div>
                    </template>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
