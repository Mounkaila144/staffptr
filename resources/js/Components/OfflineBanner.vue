<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppButton from './AppButton.vue';

const offline = ref(false);
const restored = ref(false);
const dismissed = ref(false);
const failedAction = ref(false);
let restoredTimer = null;

function goOffline() {
    offline.value = true;
    restored.value = false;
    dismissed.value = false;
}
function goOnline() {
    offline.value = false;
    failedAction.value = false;
    restored.value = true;
    window.clearTimeout(restoredTimer);
    restoredTimer = window.setTimeout(() => { restored.value = false; }, 3000);
}
function reportFailedAction() {
    if (!navigator.onLine || offline.value) failedAction.value = true;
}
defineExpose({ reportFailedAction });

onMounted(() => {
    if (!navigator.onLine) goOffline();
    window.addEventListener('offline', goOffline);
    window.addEventListener('online', goOnline);
});
onBeforeUnmount(() => {
    window.removeEventListener('offline', goOffline);
    window.removeEventListener('online', goOnline);
    window.clearTimeout(restoredTimer);
});
</script>

<template>
    <div aria-live="polite">
        <div v-if="offline && !dismissed" class="fixed inset-x-0 top-14 z-20 flex items-center gap-3 border-b border-warning bg-warning-soft px-4 py-2 text-warning md:left-52" data-testid="offline-banner">
            <p class="flex-1 font-semibold">⚠ Vous êtes hors connexion. Vous pouvez continuer votre saisie.</p>
            <button type="button" class="touch-target rounded-lg font-semibold" aria-label="Masquer le bandeau hors connexion" @click="dismissed = true">×</button>
        </div>
        <div v-if="restored" class="fixed inset-x-0 top-14 z-20 border-b border-success bg-success-soft px-4 py-3 font-semibold text-success md:left-52" data-testid="online-banner">✓ Connexion rétablie</div>
        <div v-if="failedAction" class="mt-3 rounded-xl border border-danger bg-danger-soft p-4 text-danger" data-testid="offline-failure">
            <p>L'envoi n'a pas abouti — pas de connexion. Votre rapport est conservé sur cet appareil.</p>
            <AppButton class="mt-2" variant="discret" @click="failedAction = false">Réessayer</AppButton>
        </div>
    </div>
</template>
