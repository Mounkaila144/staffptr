<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import FormField from '../../Components/FormField.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';

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
        <section class="w-full rounded-xl border border-separator bg-surface p-5 shadow-sm sm:p-8" aria-labelledby="password-title">
            <div class="mb-6 grid gap-2">
                <h1 id="password-title" class="text-screen-title">Choisissez un nouveau mot de passe</h1>
                <p class="text-ink-secondary">Ce changement est obligatoire avant d'accéder à votre espace. Utilisez au moins 12 caractères.</p>
            </div>

            <form class="grid gap-5" novalidate @submit.prevent="submit">
                <FormField ref="passwordField" v-model="form.password" id="password" label="Nouveau mot de passe" variant="password" autocomplete="new-password" :error="form.errors.password" required />
                <FormField ref="confirmationField" v-model="form.password_confirmation" id="password_confirmation" label="Confirmer le mot de passe" variant="password" autocomplete="new-password" :error="form.errors.password_confirmation" required />
                <AppButton type="submit" variant="principal" :busy="form.processing" busy-label="Enregistrement en cours">Enregistrer le mot de passe</AppButton>
            </form>
        </section>
    </AuthLayout>
</template>
