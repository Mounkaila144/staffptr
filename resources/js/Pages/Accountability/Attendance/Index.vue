<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import DataTable from '../../../Components/DataTable.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import PageHeader from '../../../Components/PageHeader.vue';
import StatCard from '../../../Components/StatCard.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    today_label: { type: String, required: true },
    expected_label: { type: String, required: true },
    can_record: { type: Boolean, required: true },
    register: { type: Array, required: true },
    history: { type: Array, required: true },
});

const page = usePage();
const busy = ref(null);
const error = computed(() => page.props.errors?.attendance ?? '');

const arrived = computed(() => props.register.filter((row) => row.record).length);
const late = computed(() => props.register.filter((row) => row.record?.late_minutes > 0).length);
const gone = computed(() => props.register.filter((row) => row.record?.left_at_label).length);

// La colonne d'action n'existe que pour qui peut enregistrer : une colonne vide intrigue.
const registerColumns = computed(() => [
    { key: 'name', label: 'Stagiaire', primary: true },
    { key: 'arrival', label: 'Arrivée' },
    { key: 'departure', label: 'Départ' },
    { key: 'status', label: 'Ponctualité' },
    props.can_record ? { key: 'actions', label: 'Enregistrer', align: 'right' } : { key: 'duration', label: 'Durée', align: 'right' },
]);
const showsCounters = computed(() => props.can_record || props.register.length > 1);
const historyColumns = [
    { key: 'date_label', label: 'Date', primary: true },
    { key: 'name', label: 'Stagiaire' },
    { key: 'arrived_at_label', label: 'Arrivée' },
    { key: 'left_at_label', label: 'Départ' },
    { key: 'duration_label', label: 'Durée', hideOnMobile: true },
    { key: 'late_label', label: 'Ponctualité' },
    { key: 'recorded_by', label: 'Enregistré par', hideOnMobile: true },
];

function record(row, kind) {
    busy.value = `${row.intern_id}-${kind}`;
    router.post(`/arrivees-departs/${row.intern_id}/${kind}`, {}, { preserveScroll: true, onFinish: () => { busy.value = null; } });
}
</script>

<template>
    <Head title="Arrivées et départs" />
    <AppLayout title="Arrivées et départs" active-navigation="team">
        <div class="grid min-w-0 gap-6">
            <PageHeader
                title="Arrivées et départs"
                :eyebrow="today_label"
                :description="can_record
                    ? 'Le stagiaire se présente au poste de la direction : un clic enregistre l’heure du serveur. Elle ne se saisit ni ne s’antidate.'
                    : 'Les heures sont enregistrées au poste de la direction ; vous les consultez ici.'"
            >
                <template #meta><p class="text-sm text-ink-muted">{{ expected_label }}</p></template>
            </PageHeader>

            <p v-if="error" class="rounded-xl border border-danger/40 bg-danger-soft p-4 font-medium text-danger" role="alert">⚠ {{ error }}</p>

            <section v-if="register.length && showsCounters" class="grid min-w-0 gap-4 sm:grid-cols-3" aria-label="Aujourd’hui">
                <StatCard label="Arrivés aujourd’hui" :value="`${arrived} / ${register.length}`" icon="login" :hint="arrived === register.length ? 'Tout le monde est là' : `${register.length - arrived} pas encore arrivé${register.length - arrived > 1 ? 's' : ''}`" />
                <StatCard label="En retard" :value="late" icon="clock" :tone="late ? 'warning' : 'success'" :hint="late ? 'Au-delà de la tolérance' : 'Aucun retard aujourd’hui'" />
                <StatCard label="Partis" :value="gone" icon="logout" tone="neutral" :hint="`${arrived - gone} encore présent${arrived - gone > 1 ? 's' : ''}`" />
            </section>

            <section class="grid min-w-0 gap-3" aria-labelledby="register-title">
                <h2 id="register-title" class="text-section-title">Aujourd’hui</h2>
                <EmptyState v-if="register.length === 0" title="Aucun stagiaire en stage actif." reason="Un stagiaire apparaît ici dès que son stage est activé." />
                <DataTable v-else :columns="registerColumns" :rows="register" row-key="intern_id" caption="Arrivées et départs du jour">
                    <template #cell-arrival="{ row }">{{ row.record?.arrived_at_label ?? '—' }}</template>
                    <template #cell-departure="{ row }">{{ row.record?.left_at_label ?? '—' }}</template>
                    <template #cell-status="{ row }">
                        <span v-if="!row.record" class="text-ink-muted">Pas encore arrivé</span>
                        <span v-else class="inline-flex min-h-7 items-center gap-1 whitespace-nowrap rounded-full border px-2.5 text-[0.8125rem] font-semibold" :class="row.record.late_minutes > 0 ? 'border-warning bg-warning-soft text-warning' : 'border-success bg-success-soft text-success'">
                            <span aria-hidden="true">{{ row.record.late_minutes > 0 ? '⚠' : '✓' }}</span>{{ row.record.late_label }}
                        </span>
                    </template>
                    <template #cell-actions="{ row }">
                        <template v-if="can_record">
                            <AppButton v-if="!row.record" variant="principal" :busy="busy === `${row.intern_id}-arrivee`" busy-label="Enregistrement" @click="record(row, 'arrivee')">Arrivée<span class="sr-only"> de {{ row.name }}</span></AppButton>
                            <AppButton v-else-if="!row.record.left_at_label" variant="secondaire" :busy="busy === `${row.intern_id}-depart`" busy-label="Enregistrement" @click="record(row, 'depart')">Départ<span class="sr-only"> de {{ row.name }}</span></AppButton>
                            <span v-else class="text-sm text-ink-muted">Journée close · {{ row.record.duration_label }}</span>
                        </template>
                    </template>
                    <template #cell-duration="{ row }">{{ row.record?.duration_label ?? '—' }}</template>
                </DataTable>
            </section>

            <section class="grid min-w-0 gap-3" aria-labelledby="history-title">
                <h2 id="history-title" class="text-section-title">Historique</h2>
                <EmptyState v-if="history.length === 0" title="Aucune journée enregistrée pour l’instant." reason="Les arrivées et départs s’ajoutent ici jour après jour." />
                <DataTable v-else :columns="historyColumns" :rows="history" caption="Historique des arrivées et départs">
                    <template #cell-left_at_label="{ row }">{{ row.left_at_label ?? 'Non enregistré' }}</template>
                    <template #cell-duration_label="{ row }">{{ row.duration_label ?? '—' }}</template>
                    <template #cell-late_label="{ row }"><span :class="row.late_minutes > 0 ? 'font-semibold text-warning' : ''">{{ row.late_minutes > 0 ? '⚠ ' : '' }}{{ row.late_label }}</span></template>
                </DataTable>
            </section>
        </div>
    </AppLayout>
</template>
