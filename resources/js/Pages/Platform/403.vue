<script setup>
import { Head } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';
</script>

<template>
    <Head title="Accès refusé" />

    <AuthLayout>
        <section class="w-full rounded-xl border border-separator bg-surface p-6 shadow-sm sm:p-10">
            <p class="text-sm font-semibold uppercase tracking-widest text-primary">PTR Staff</p>
            <h1 class="mt-3 text-screen-title">Accès refusé</h1>
            <p class="mt-4 text-ink-secondary">
                Vous n'avez pas accès à cette page. Revenez à l'accueil ou demandez l'accès à la
                direction.
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
