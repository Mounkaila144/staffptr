<script setup>
import { nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({ clients: { type: Array, required: true } });

const formOpen = ref(false);
const editingId = ref(null);
const nameField = ref(null);
const form = useForm({ name: '', phone: '', contact: '', notes: '', is_active: true });

async function openForm(client = null) {
    editingId.value = client?.id ?? null;
    form.name = client?.name ?? '';
    form.phone = client?.phone ?? '';
    form.contact = client?.contact ?? '';
    form.notes = client?.notes ?? '';
    form.is_active = client?.is_active ?? true;
    form.clearErrors();
    formOpen.value = true;
    await nextTick();
    nameField.value?.focus();
}

function closeForm() {
    formOpen.value = false;
    editingId.value = null;
    form.reset();
}

function submit() {
    const options = { preserveScroll: true, onSuccess: closeForm, onError: () => nextTick(() => nameField.value?.focus()) };
    if (editingId.value) form.patch(`/finances/clients/${editingId.value}`, options);
    else form.post('/finances/clients', options);
}
</script>

<template>
    <AppLayout title="Clients" active-navigation="finance">
        <div class="grid min-w-0 gap-8">
            <header class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                <div class="grid min-w-0 gap-2">
                    <h1 class="break-words text-page-title">Clients</h1>
                    <p class="text-ink-secondary">Répertoire des clients utilisés par les contrats et les encaissements.</p>
                </div>
                <AppButton variant="principal" @click="openForm()">Ajouter un client</AppButton>
            </header>

            <form v-if="formOpen" class="grid min-w-0 gap-4 rounded-xl border border-primary bg-surface p-4" @submit.prevent="submit">
                <h2 class="text-section-title">{{ editingId ? 'Modifier le client' : 'Nouveau client' }}</h2>
                <FormField ref="nameField" id="client-name" v-model="form.name" label="Nom" required :error="form.errors.name" />
                <FormField id="client-phone" v-model="form.phone" label="Téléphone" type="tel" required :error="form.errors.phone" />
                <FormField id="client-contact" v-model="form.contact" label="Personne de contact" :error="form.errors.contact" />
                <label class="grid min-w-0 gap-2 font-semibold" for="client-notes">Notes
                    <textarea id="client-notes" v-model="form.notes" class="min-h-24 rounded-lg border border-separator p-3" />
                </label>
                <label class="touch-target flex items-center gap-3 rounded-lg border border-separator px-3">
                    <input v-model="form.is_active" type="checkbox"> Client actif
                </label>
                <div class="flex flex-wrap gap-2">
                    <AppButton type="submit" variant="principal" :busy="form.processing">Enregistrer</AppButton>
                    <AppButton type="button" variant="secondaire" @click="closeForm">Annuler</AppButton>
                </div>
            </form>

            <EmptyState v-if="clients.length === 0" title="Aucun client enregistré." reason="Ajoutez le premier client avant de créer un contrat." action-label="Ajouter un client" @action="openForm()" />
            <ul v-else class="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <li v-for="client in clients" :key="client.id" class="grid min-w-0 gap-3 rounded-xl border border-separator bg-surface p-4">
                    <div class="flex min-w-0 flex-wrap justify-between gap-2">
                        <h2 class="break-words text-card-title">{{ client.name }}</h2>
                        <StatusBadge :status="client.is_active ? 'actif' : 'inactif'" />
                    </div>
                    <p class="break-all">{{ client.phone }}</p>
                    <p class="text-sm text-ink-secondary">{{ client.contracts_count }} contrat(s)</p>
                    <AppButton variant="secondaire" @click="openForm(client)">Modifier</AppButton>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
