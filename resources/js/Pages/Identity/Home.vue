<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
</script>

<template>
    <Head title="Accueil" />
    <AppLayout title="Accueil" active-navigation="home">
        <section class="grid gap-4" aria-labelledby="home-title">
            <h1 id="home-title" class="text-screen-title">Bienvenue dans PTR Staff</h1>
            <p class="text-ink-secondary">Votre espace est prêt. Les modules métier apparaîtront ici selon vos droits.</p>
            <Link href="/deconnexion" method="post" as="button" class="touch-target justify-self-start rounded-lg px-3 font-semibold text-primary underline-offset-4 hover:underline">Se déconnecter</Link>
        </section>
    </AppLayout>
</template>
