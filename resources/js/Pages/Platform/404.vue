<script setup>
import { Head } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
</script>

<template>
    <Head title="Page introuvable" />

    <AuthLayout>
        <section class="w-full rounded-xl border border-separator bg-surface p-6 shadow-sm sm:p-10">
            <p class="text-sm font-semibold uppercase tracking-widest text-primary">PTR Staff</p>
            <h1 class="mt-3 text-screen-title">Page introuvable</h1>
            <p class="mt-4 text-ink-secondary">
                Cette page n'existe pas ou a changé d'adresse. Vérifiez l'adresse ou revenez à
                l'accueil.
            </p>
            <a
                href="/"
                class="touch-target mt-8 inline-flex min-h-12 items-center rounded-lg bg-primary px-5 font-semibold text-white"
            >
                Revenir à l'accueil
            </a>
        </section>
    </AuthLayout>
</template>
