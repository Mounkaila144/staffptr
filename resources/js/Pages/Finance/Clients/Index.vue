<script setup>
import { nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AppModal from '../../../Components/AppModal.vue';
import DataTable from '../../../Components/DataTable.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({ clients: { type: Array, required: true } });

const formOpen = ref(false);
const editingId = ref(null);
const nameField = ref(null);
const columns = [
    { key: 'name', label: 'Client', primary: true },
    { key: 'phone', label: 'Téléphone' },
    { key: 'contact', label: 'Contact' },
    { key: 'contracts_count', label: 'Contrats' },
    { key: 'is_active', label: 'État' },
    { key: 'actions', label: 'Actions', align: 'right' },
];
const form = useForm({ name: '', phone: '', contact: '', notes: '', is_active: true });

async function openForm(client = null) {
    editingId.value = client?.id ?? null;
    form.name = client?.name ?? '';
    // Le champ affiche déjà l’indicatif +227 : seuls les huit chiffres restent à éditer.
    form.phone = (client?.phone ?? '').replace(/^\+227/, '');
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
        <div class="grid min-w-0 gap-6">
            <PageHeader title="Clients" description="Répertoire des clients utilisés par les contrats et les encaissements.">
                <template #actions>
                    <!-- L’entrée de menu s’appelle « Clients et factures » : la suite du parcours de vente doit être atteignable d’ici. -->
                    <AppButton variant="secondaire" href="/finances/contrats">Contrats</AppButton>
                    <AppButton variant="secondaire" href="/finances/factures">Factures</AppButton>
                    <AppButton variant="secondaire" href="/finances/encaissements">Encaissements</AppButton>
                    <AppButton variant="principal" @click="openForm()">Ajouter un client</AppButton>
                </template>
            </PageHeader>

            <EmptyState v-if="clients.length === 0" title="Aucun client enregistré." reason="Ajoutez le premier client avant de créer un contrat." action-label="Ajouter un client" @action="openForm()" />
            <DataTable v-else :columns="columns" :rows="clients" caption="Liste des clients">
                <template #cell-is_active="{ row }"><StatusBadge :status="row.is_active ? 'actif' : 'inactif'" /></template>
                <template #cell-contracts_count="{ row }">{{ row.contracts_count }} contrat{{ row.contracts_count > 1 ? 's' : '' }}</template>
                <template #cell-actions="{ row }"><AppButton variant="discret" @click="openForm(row)">Modifier<span class="sr-only"> {{ row.name }}</span></AppButton></template>
            </DataTable>

            <AppModal :open="formOpen" :title="editingId ? 'Modifier le client' : 'Nouveau client'" @update:open="(open) => !open && closeForm()">
                <form id="client-form" class="grid min-w-0 gap-4" @submit.prevent="submit">
                    <FormField ref="nameField" id="client-name" v-model="form.name" label="Nom" required :error="form.errors.name" />
                    <FormField id="client-phone" v-model="form.phone" label="Téléphone" variant="phone" required :error="form.errors.phone" />
                    <FormField id="client-contact" v-model="form.contact" label="Personne de contact" :error="form.errors.contact" />
                    <label class="grid min-w-0 gap-2 font-semibold" for="client-notes">Notes
                        <textarea id="client-notes" v-model="form.notes" class="min-h-24 rounded-lg border border-separator p-3 font-normal" />
                    </label>
                    <label class="touch-target flex items-center gap-3 rounded-lg border border-separator px-3">
                        <input v-model="form.is_active" type="checkbox"> Client actif
                    </label>
                </form>
                <template #footer>
                    <AppButton type="button" variant="secondaire" @click="closeForm">Annuler</AppButton>
                    <AppButton type="submit" form="client-form" variant="principal" :busy="form.processing">Enregistrer</AppButton>
                </template>
            </AppModal>
        </div>
    </AppLayout>
</template>
