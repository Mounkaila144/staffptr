<script setup>
import { useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({ comparison: { type: Object, required: true }, categories: { type: Array, required: true } });
const form = useForm({ category_id: '', month: props.comparison.month, budget_amount: 0 });
function submit() { form.post('/finances/budgets-mensuels', { preserveScroll: true }); }
</script>

<template>
    <AppLayout title="Budgets mensuels" active-navigation="finance">
        <div class="grid min-w-0 gap-6">
            <header class="grid gap-2"><h1 class="text-page-title">Budgets mensuels</h1><p class="text-ink-secondary">Comparaison au réalisé payé. L'absence de budget ne bloque jamais une dépense.</p></header>
            <form class="grid gap-4 rounded-xl border border-separator bg-surface p-4" @submit.prevent="submit">
                <label class="grid gap-2 font-semibold">Catégorie<select v-model="form.category_id" class="touch-target rounded-lg border border-separator px-3" required><option value="" disabled>Choisir</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></label>
                <FormField id="budget-month" v-model="form.month" type="month" label="Mois" required :error="form.errors.month" />
                <FormField id="budget-amount" v-model="form.budget_amount" type="number" min="0" label="Budget en XOF" required :error="form.errors.budget_amount" />
                <AppButton type="submit" variant="principal" :busy="form.processing">Enregistrer le budget</AppButton>
            </form>
            <p v-if="comparison.empty_message" class="rounded-lg border border-separator bg-neutral-soft p-4 font-semibold">{{ comparison.empty_message }}</p>
            <EmptyState v-if="comparison.rows.length === 0" title="Aucune dépense réalisée pour ce mois." reason="Les dépenses restent possibles même sans budget." />
            <ul v-else class="grid gap-3">
                <li v-for="row in comparison.rows" :key="row.category_id" class="grid gap-2 rounded-xl border border-separator bg-surface p-4 sm:grid-cols-4">
                    <strong>{{ row.category_name }}</strong><span>Budget : {{ row.budget_amount_label }}</span><span>Réalisé : {{ row.actual_amount_label }} · {{ row.actual_percentage_label }}</span><span :class="row.is_exceeded ? 'font-bold text-danger' : 'font-semibold'">{{ row.status_label }} · {{ row.variance_amount_label }}</span>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
