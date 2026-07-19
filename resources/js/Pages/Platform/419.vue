<script setup>
import { Head } from '@inertiajs/vue3';
import { useDraft } from '../../Composables/useDraft';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const draft = useDraft('session-expired', 'anonymous', 'current');
</script>

<template>
    <Head title="Session expirée" />

    <AuthLayout>
        <section class="w-full rounded-xl border border-separator bg-surface p-6 shadow-sm sm:p-10" :data-local-draft="draft.hasStoredDrafts.value ? 'preserved' : 'available'">
            <p class="text-sm font-semibold uppercase tracking-widest text-primary">PTR Staff</p>
            <h1 class="mt-3 text-screen-title">Votre session a expiré</h1>
            <p class="mt-4 text-ink-secondary">
                Revenez à l'accueil pour vous reconnecter. Cette page ne supprime pas votre
                brouillon conservé dans ce navigateur.
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
