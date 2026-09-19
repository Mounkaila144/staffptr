<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import AppButton from '../../../../Components/AppButton.vue';

defineProps({
    slots: { type: Array, required: true },
    nextSlot: { type: String, default: null },
    pendingRequests: { type: Array, required: true },
    canManage: { type: Boolean, default: false },
    success: { type: String, default: null },
});

const slotForm = useForm({ weekday: 2, start_time: '10:00' });

function addSlot() {
    slotForm.post('/creneaux-suivi', { preserveScroll: true });
}
</script>

<template>
    <Head title="Créneaux de suivi" />
    <AppLayout title="Créneaux de suivi" active-navigation="internship">
        <div class="grid min-w-0 gap-5">
            <header>
                <h1 class="text-screen-title">Créneaux de suivi</h1>
                <p class="text-ink-secondary">
                    Les demandes non urgentes sont rassemblées et présentées au créneau suivant. Un blocage
                    urgent est transmis immédiatement.
                </p>
            </header>

            <p v-if="success" class="rounded-lg border border-success bg-success-soft p-3 text-success" role="status">
                {{ success }}
            </p>

            <section class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Mes créneaux</h2>
                <ul v-if="slots.length" class="grid gap-1">
                    <li v-for="slot in slots" :key="slot.id" class="text-ink-secondary">
                        {{ slot.weekday_label }} à {{ slot.start_time }}
                    </li>
                </ul>
                <p v-else class="text-ink-secondary">
                    Aucun créneau configuré. Sans créneau, les demandes vous parviennent à l’unité, dès leur envoi.
                </p>
                <p v-if="nextSlot" class="text-ink-secondary">Prochain créneau : {{ nextSlot }}.</p>

                <form v-if="canManage" class="grid gap-2 border-t border-separator pt-3 sm:grid-cols-3" @submit.prevent="addSlot">
                    <label class="grid gap-1 font-semibold" for="slot-weekday">Jour
                        <select id="slot-weekday" v-model="slotForm.weekday" class="touch-target rounded-lg border border-separator px-3">
                            <option :value="1">Lundi</option>
                            <option :value="2">Mardi</option>
                            <option :value="3">Mercredi</option>
                            <option :value="4">Jeudi</option>
                            <option :value="5">Vendredi</option>
                            <option :value="6">Samedi</option>
                            <option :value="7">Dimanche</option>
                        </select>
                    </label>
                    <label class="grid gap-1 font-semibold" for="slot-time">Heure
                        <input id="slot-time" v-model="slotForm.start_time" type="time" class="touch-target rounded-lg border border-separator px-3" />
                    </label>
                    <div class="grid items-end">
                        <AppButton type="submit" :disabled="slotForm.processing">Ajouter le créneau</AppButton>
                    </div>
                    <p v-if="slotForm.errors.start_time" class="text-danger sm:col-span-3" role="alert">
                        {{ slotForm.errors.start_time }}
                    </p>
                </form>
            </section>

            <!-- Le stagiaire voit quand sa demande sera examinée, pour ne pas avoir à relancer. -->
            <section v-if="pendingRequests.length" class="grid min-w-0 gap-2 rounded-xl border border-separator bg-surface p-4">
                <h2 class="text-section-title">Mes demandes en attente</h2>
                <ul class="grid gap-2">
                    <li v-for="request in pendingRequests" :key="request.blocker_id" class="grid gap-0.5 rounded-lg bg-neutral-soft p-3">
                        <span class="break-words font-semibold">{{ request.problem }}</span>
                        <span class="text-ink-secondary">
                            {{ request.delivered ? 'Transmise à votre tuteur.' : `Sera examinée le ${request.scheduled_for}.` }}
                        </span>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
