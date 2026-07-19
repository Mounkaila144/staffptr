<script setup>
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Session expirée" />

    <main class="flex min-h-screen items-center justify-center bg-slate-950 px-4 py-12 text-slate-100">
        <section class="w-full max-w-xl rounded-3xl border border-slate-700 bg-slate-900 p-6 shadow-2xl sm:p-10">
            <p class="text-sm font-semibold uppercase tracking-widest text-amber-300">PTR Staff</p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Votre session a expiré</h1>
            <p class="mt-4 text-base leading-7 text-slate-300">
                Revenez à l'accueil pour vous reconnecter. Cette page ne supprime pas votre
                brouillon conservé dans ce navigateur.
            </p>
            <a
                href="/"
                class="mt-8 inline-flex min-h-11 items-center rounded-xl bg-emerald-400 px-5 font-semibold text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-300"
            >
                Revenir à l'accueil
            </a>
        </section>
    </main>
</template>
