<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    reference: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head title="Application indisponible" />

    <main class="flex min-h-screen items-center justify-center bg-slate-950 px-4 py-12 text-slate-100">
        <section class="w-full max-w-xl rounded-3xl border border-slate-700 bg-slate-900 p-6 shadow-2xl sm:p-10">
            <p class="text-sm font-semibold uppercase tracking-widest text-amber-300">PTR Staff</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                L'application rencontre un problème
            </h1>
            <p class="mt-4 text-base leading-7 text-slate-300">
                Réessayez dans quelques instants. Si le problème continue, communiquez la
                référence ci-dessous à la direction.
            </p>
            <p class="mt-5 rounded-xl border border-slate-600 bg-slate-950 px-4 py-3 text-sm text-slate-200">
                Référence : <strong class="font-semibold text-white">{{ reference }}</strong>
            </p>
            <a
                href="/"
                class="mt-8 inline-flex min-h-11 items-center rounded-xl bg-emerald-400 px-5 font-semibold text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-300"
            >
                Réessayer depuis l'accueil
            </a>
        </section>
    </main>
</template>
