<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import FormField from '../../Components/FormField.vue';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const phoneField = ref(null);
const passwordField = ref(null);
const form = useForm({
    phone: '',
    password: '',
});

function submit() {
    form.post('/connexion', {
        preserveScroll: true,
        onError: async (errors) => {
            await nextTick();
            (errors.phone ? phoneField : passwordField).value?.focus();
        },
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Connexion" />
    <AuthLayout>
        <section class="w-full rounded-xl border border-separator bg-surface p-5 shadow-sm sm:p-8" aria-labelledby="login-title">
            <div class="mb-6 grid gap-2">
                <h1 id="login-title" class="text-screen-title">Connexion</h1>
                <p class="text-ink-secondary">Entrez votre numéro du Niger et votre mot de passe.</p>
            </div>

            <form class="grid gap-5" novalidate @submit.prevent="submit">
                <FormField ref="phoneField" v-model="form.phone" id="phone" label="Numéro de téléphone" variant="phone" autocomplete="tel" placeholder="90 12 34 56" :error="form.errors.phone" required />
                <FormField ref="passwordField" v-model="form.password" id="password" label="Mot de passe" variant="password" autocomplete="current-password" :error="form.errors.password" required />
                <AppButton type="submit" variant="principal" :busy="form.processing" busy-label="Connexion en cours">Se connecter</AppButton>
            </form>
        </section>
    </AuthLayout>
</template>
