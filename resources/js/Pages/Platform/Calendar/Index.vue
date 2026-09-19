<script setup>
import { nextTick, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import EmptyState from '../../../Components/EmptyState.vue';
import FormField from '../../../Components/FormField.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    period: { type: Object, required: true },
    days: { type: Array, required: true },
    workingDays: { type: Array, required: true },
    holidays: { type: Array, required: true },
});

const creationOpen = ref(false);
const creationLabelField = ref(null);
const editingId = ref(null);
const creationForm = useForm({
    label: '',
    date: '',
    calendar_year: props.period.year,
    calendar_month: props.period.month,
});
const editForm = useForm({
    label: '',
    date: '',
    calendar_year: props.period.year,
    calendar_month: props.period.month,
});

function monthHref(target) {
    return `/calendrier?year=${target.year}&month=${target.month}`;
}

async function openCreation() {
    creationOpen.value = true;
    await nextTick();
    creationLabelField.value?.focus();
}

function submitCreation() {
    creationForm.post('/calendrier/jours-feries', {
        preserveScroll: true,
        onSuccess: () => {
            creationForm.reset('label', 'date');
            creationOpen.value = false;
        },
        onError: () => nextTick(() => creationLabelField.value?.focus()),
    });
}

function startEditing(holiday) {
    editingId.value = holiday.id;
    editForm.label = holiday.label;
    editForm.date = holiday.date;
    editForm.clearErrors();
}

function cancelEditing() {
    editingId.value = null;
    editForm.clearErrors();
}

function submitEdit(holiday) {
    editForm.patch(`/calendrier/jours-feries/${holiday.id}`, {
        preserveScroll: true,
        onSuccess: () => cancelEditing(),
    });
}

function changeActivity(holiday) {
    const action = holiday.is_active ? 'desactiver' : 'reactiver';
    router.patch(`/calendrier/jours-feries/${holiday.id}/${action}`, {
        calendar_year: props.period.year,
        calendar_month: props.period.month,
    }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Calendrier" back-label="Accueil" back-href="/" active-navigation="calendar">
        <div class="grid min-w-0 gap-6">
            <header class="grid gap-2">
                <p class="text-sm font-semibold uppercase tracking-wide text-primary">Jours travaillés et fermetures</p>
                <h1 class="text-page-title">Calendrier de l’entreprise</h1>
                <p class="max-w-2xl text-ink-secondary">Les rapports ne sont attendus que pendant les jours marqués comme travaillés.</p>
            </header>

            <section class="grid gap-3 rounded-xl border border-separator bg-surface p-4" aria-labelledby="working-days-title">
                <div>
                    <h2 id="working-days-title" class="text-card-title">Semaine de travail</h2>
                    <p class="text-sm text-ink-secondary">Ces jours se modifient dans les paramètres généraux.</p>
                </div>
                <ul class="flex flex-wrap gap-2" aria-label="Jours travaillés">
                    <li v-for="day in workingDays" :key="day.code" class="rounded-full bg-success-soft px-3 py-2 text-sm font-semibold text-success">
                        {{ day.label }}
                    </li>
                </ul>
            </section>

            <section class="grid min-w-0 gap-4 rounded-xl border border-separator bg-surface p-3 sm:p-4" aria-labelledby="calendar-title">
                <div class="flex items-center justify-between gap-2">
                    <a :href="monthHref(period.previous)" class="touch-target inline-flex items-center rounded-lg border border-primary px-3 font-semibold text-primary" aria-label="Afficher le mois précédent">←</a>
                    <h2 id="calendar-title" class="text-center text-card-title">{{ period.label }}</h2>
                    <a :href="monthHref(period.next)" class="touch-target inline-flex items-center rounded-lg border border-primary px-3 font-semibold text-primary" aria-label="Afficher le mois suivant">→</a>
                </div>

                <div class="grid min-w-0 grid-cols-7 gap-1 text-center text-[11px] font-semibold text-ink-secondary sm:text-sm" aria-hidden="true">
                    <span v-for="weekday in ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']" :key="weekday">{{ weekday }}</span>
                </div>
                <ol class="grid min-w-0 grid-cols-7 gap-1" aria-label="Jours du mois">
                    <li v-for="empty in period.leading_empty_days" :key="`empty-${empty}`" aria-hidden="true" class="min-w-0" />
                    <li
                        v-for="day in days"
                        :key="day.date"
                        class="min-h-20 min-w-0 rounded-lg border p-1 text-center sm:min-h-24 sm:p-2"
                        :class="day.is_working_day ? 'border-separator bg-surface' : 'border-warning bg-warning-soft'"
                        :aria-label="`${day.weekday_label} ${day.day_number}, ${day.is_working_day ? 'jour travaillé' : day.status_label}`"
                    >
                        <span class="block text-sm font-bold">{{ day.day_number }}</span>
                        <span v-if="day.is_working_day" class="mt-1 hidden text-xs text-success sm:block">Travaillé</span>
                        <span v-else class="mt-1 block break-words text-[10px] font-semibold leading-tight text-warning sm:text-xs">{{ day.status_label }}</span>
                    </li>
                </ol>
                <p class="text-xs text-ink-secondary">Chaque fermeture est indiquée par un libellé, en plus de sa couleur.</p>
            </section>

            <section class="grid gap-4" aria-labelledby="holidays-title">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="holidays-title" class="text-section-title">Jours fériés de {{ period.year }}</h2>
                        <p class="text-sm text-ink-secondary">Ajoutez une fermeture ponctuelle ou corrigez un jour existant.</p>
                    </div>
                    <AppButton variant="principal" @click="openCreation">Ajouter un jour férié</AppButton>
                </div>

                <form v-if="creationOpen" class="grid gap-4 rounded-xl border border-primary bg-primary-soft p-4 md:grid-cols-2" @submit.prevent="submitCreation">
                    <FormField ref="creationLabelField" id="holiday-label" v-model="creationForm.label" label="Libellé" required :error="creationForm.errors.label" placeholder="Ex. Fermeture exceptionnelle" />
                    <FormField id="holiday-date" v-model="creationForm.date" label="Date d’effet" variant="date" required :error="creationForm.errors.date" />
                    <div class="flex flex-wrap gap-2 md:col-span-2">
                        <AppButton type="submit" variant="principal" :busy="creationForm.processing" busy-label="Ajout en cours">Ajouter</AppButton>
                        <AppButton variant="secondaire" @click="creationOpen = false">Annuler</AppButton>
                    </div>
                </form>

                <EmptyState
                    v-if="holidays.length === 0"
                    title="Aucun jour férié saisi"
                    reason="Aucun jour férié saisi pour cette période. Ajoutez le premier jour de fermeture."
                    action-label="Ajouter un jour férié"
                    @action="openCreation"
                />

                <ul v-else class="grid gap-3">
                    <li v-for="holiday in holidays" :key="holiday.id" class="grid gap-3 rounded-xl border border-separator bg-surface p-4">
                        <form v-if="editingId === holiday.id" class="grid gap-4 md:grid-cols-2" @submit.prevent="submitEdit(holiday)">
                            <FormField :id="`holiday-label-${holiday.id}`" v-model="editForm.label" label="Libellé" required :error="editForm.errors.label" />
                            <FormField :id="`holiday-date-${holiday.id}`" v-model="editForm.date" label="Date d’effet" variant="date" required :error="editForm.errors.date" />
                            <div class="flex flex-wrap gap-2 md:col-span-2">
                                <AppButton type="submit" variant="principal" :busy="editForm.processing" busy-label="Enregistrement">Enregistrer</AppButton>
                                <AppButton variant="secondaire" @click="cancelEditing">Annuler</AppButton>
                            </div>
                        </form>
                        <template v-else>
                            <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="break-words font-semibold">{{ holiday.label }}</p>
                                    <p class="text-sm text-ink-secondary">Date d’effet : {{ holiday.display_date }}</p>
                                </div>
                                <StatusBadge :tone="holiday.is_active ? 'success' : 'neutral'">{{ holiday.is_active ? 'Actif' : 'Désactivé' }}</StatusBadge>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <AppButton variant="secondaire" @click="startEditing(holiday)">Modifier</AppButton>
                                <AppButton :variant="holiday.is_active ? 'destructeur' : 'secondaire'" @click="changeActivity(holiday)">
                                    {{ holiday.is_active ? 'Désactiver' : 'Réactiver' }}
                                </AppButton>
                            </div>
                        </template>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
