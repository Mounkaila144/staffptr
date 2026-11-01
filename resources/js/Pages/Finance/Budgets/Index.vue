<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({ comparison: { type: Object, required: true }, categories: { type: Array, required: true } });
const form = useForm({ category_id: '', month: props.comparison.month, budget_amount: 0 });
function submit() { form.post('/finances/budgets-mensuels', { preserveScroll: true }); }
// L'écart est dit en mots : « reste » sous le budget, « dépassé de » au-delà.
function varianceLabel(row) {
    if (row.variance_amount === null) {
        return row.status_label;
    }
    const amount = `${new Intl.NumberFormat('fr-FR').format(Math.abs(row.variance_amount))} FCFA`;

    return row.is_exceeded ? `Budget dépassé de ${amount}` : `Dans le budget · reste ${amount}`;
}
// Mois comparé : sans ce sélecteur, seul le mois courant (ou celui du dernier budget saisi) était consultable.
const shownMonth = ref(props.comparison.month);
watch(() => props.comparison.month, (month) => { shownMonth.value = month; });
watch(shownMonth, () => showMonth());
function showMonth() {
    if (/^\d{4}-\d{2}$/.test(shownMonth.value) && shownMonth.value !== props.comparison.month) {
        router.get('/finances/budgets-mensuels', { month: shownMonth.value }, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout title="Budgets mensuels" active-navigation="finance">
        <div class="grid min-w-0 gap-6">
            <header class="grid gap-2"><h1 class="text-page-title">Budgets mensuels</h1><p class="text-ink-secondary">Comparaison au réalisé payé. L'absence de budget ne bloque jamais une dépense.</p><a href="/finances/charges-fixes" class="w-fit font-semibold text-primary underline underline-offset-4">Voir et modifier les charges fixes</a></header>
            <form class="grid gap-4 rounded-xl border border-separator bg-surface p-4" @submit.prevent="submit">
                <label class="grid gap-2 font-semibold">Catégorie<select v-model="form.category_id" class="touch-target rounded-lg border border-separator px-3" required :aria-invalid="Boolean(form.errors.category_id)"><option value="" disabled>Choisir</option><option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></label>
                <p v-if="form.errors.category_id" class="text-sm font-semibold text-danger">⚠ {{ form.errors.category_id }}</p>
                <FormField id="budget-month" v-model="form.month" variant="month" label="Mois" required :error="form.errors.month" />
                <FormField id="budget-amount" v-model="form.budget_amount" variant="money" label="Montant du budget (francs CFA)" required :error="form.errors.budget_amount" />
                <AppButton type="submit" variant="principal" :busy="form.processing">Enregistrer le budget</AppButton>
            </form>
            <div class="flex min-w-0 flex-wrap items-end gap-3">
                <h2 class="text-section-title">Réalisé de {{ comparison.month_label }}</h2>
                <div class="w-48"><FormField id="shown-month" v-model="shownMonth" variant="month" label="Mois affiché" /></div>
            </div>
            <p v-if="comparison.empty_message" class="rounded-lg border border-separator bg-neutral-soft p-4 font-semibold">{{ comparison.empty_message }}</p>
            <EmptyState v-if="comparison.rows.length === 0" title="Aucune dépense réalisée pour ce mois." reason="Les dépenses restent possibles même sans budget." />
            <ul v-else class="grid gap-3">
                <li v-for="row in comparison.rows" :key="row.category_id" class="grid gap-2 rounded-xl border border-separator bg-surface p-4 sm:grid-cols-4">
                    <strong>{{ row.category_name }}</strong><span>Budget : {{ row.budget_amount_label }}</span><span>Réalisé : {{ row.actual_amount_label }} · {{ row.actual_percentage_label }}</span><span :class="row.is_exceeded ? 'font-bold text-danger' : 'font-semibold'">{{ varianceLabel(row) }}</span>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
