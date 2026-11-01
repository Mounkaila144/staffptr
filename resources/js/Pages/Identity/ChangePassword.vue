<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import FormField from '../../Components/FormField.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';

defineProps({
    // Vrai à la première connexion ou après une réinitialisation : aucune autre page n'est accessible.
    forced: { type: Boolean, default: true },
});

const passwordField = ref(null);
const confirmationField = ref(null);
const form = useForm({
    password: '',
    password_confirmation: '',
});

function submit() {
    form.patch('/mot-de-passe', {
        preserveScroll: true,
        onError: async (errors) => {
            await nextTick();
            (errors.password ? passwordField : confirmationField).value?.focus();
        },
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Changer votre mot de passe" />
    <AuthLayout>
        <section class="w-full rounded-2xl border border-separator bg-surface p-6 shadow-raised sm:p-8" aria-labelledby="password-title">
            <div class="mb-6 grid gap-2">
                <h1 id="password-title" class="text-screen-title">Choisissez un nouveau mot de passe</h1>
                <p v-if="forced" class="text-ink-secondary">Ce changement est obligatoire avant d'accéder à votre espace. Utilisez au moins 8 caractères ; des chiffres seuls suffisent.</p>
                <p v-else class="text-ink-secondary">Utilisez au moins 8 caractères ; des chiffres seuls suffisent. Vos autres sessions ouvertes seront fermées.</p>
            </div>

            <form class="grid gap-5" novalidate @submit.prevent="submit">
                <FormField ref="passwordField" v-model="form.password" id="password" label="Nouveau mot de passe" variant="password" autocomplete="new-password" :error="form.errors.password" required />
                <FormField ref="confirmationField" v-model="form.password_confirmation" id="password_confirmation" label="Confirmer le mot de passe" variant="password" autocomplete="new-password" :error="form.errors.password_confirmation" required />
                <AppButton type="submit" variant="principal" :busy="form.processing" busy-label="Enregistrement en cours">Enregistrer le mot de passe</AppButton>
            </form>
            <Link v-if="!forced" href="/" class="touch-target mt-4 inline-flex items-center font-semibold text-primary">Retour à l’accueil sans changer</Link>
        </section>
    </AuthLayout>
</template>
