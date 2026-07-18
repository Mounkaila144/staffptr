<script setup>
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Fondation applicative" />

    <main class="flex min-h-screen items-center justify-center px-4 py-12">
        <section class="w-full max-w-2xl rounded-3xl border border-slate-700 bg-slate-900 p-6 shadow-2xl sm:p-10">
            <p class="text-sm font-semibold uppercase tracking-widest text-emerald-300">
                PTR Staff
            </p>
            <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                Fondation applicative opérationnelle
            </h1>
            <p class="mt-4 max-w-prose text-base leading-7 text-slate-300">
                Laravel, Inertia, Vue, Vite et Tailwind sont correctement câblés. Les prochains
                modules peuvent être construits sur ce socle testé.
            </p>
            <a
                href="/up"
                class="mt-8 inline-flex min-h-11 items-center rounded-xl bg-emerald-400 px-5 font-semibold text-slate-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-300"
            >
                Vérifier l’état du service
            </a>
        </section>
    </main>
</template>
