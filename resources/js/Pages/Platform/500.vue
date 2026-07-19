<script setup>
import { Head } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

defineProps({
    reference: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head title="Application indisponible" />

    <AuthLayout>
        <section class="w-full rounded-xl border border-separator bg-surface p-6 shadow-sm sm:p-10">
            <p class="text-sm font-semibold uppercase tracking-widest text-primary">PTR Staff</p>
            <h1 class="mt-3 text-screen-title">
                L'application rencontre un problème
            </h1>
            <p class="mt-4 text-ink-secondary">
                Réessayez dans quelques instants. Si le problème continue, communiquez la
                référence ci-dessous à la direction.
            </p>
            <p class="mt-5 rounded-xl border border-separator bg-canvas px-4 py-3 text-sm text-ink-secondary">
                Référence : <strong class="font-semibold text-ink">{{ reference }}</strong>
            </p>
            <a
                href="/"
                class="touch-target mt-8 inline-flex min-h-12 items-center rounded-lg bg-primary px-5 font-semibold text-white"
            >
                Réessayer depuis l'accueil
            </a>
        </section>
    </AuthLayout>
</template>
