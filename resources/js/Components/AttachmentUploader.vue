<script setup>
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    attachableType: { type: String, default: 'person' },
    attachableId: { type: Number, required: true },
    allowedTypes: { type: Array, default: () => ['pdf', 'jpeg', 'png', 'webp', 'heic'] },
    maxSizeBytes: { type: Number, required: true },
});
const emit = defineEmits(['uploaded']);
const fileInput = ref(null);
const error = ref('');
const progress = ref(0);
const isUploading = ref(false);
const uploadedAttachment = ref(null);

const mimeTypes = {
    pdf: 'application/pdf',
    jpeg: 'image/jpeg',
    png: 'image/png',
    webp: 'image/webp',
    heic: 'image/heic,image/heif',
};
const accept = computed(() => props.allowedTypes.flatMap((type) => (mimeTypes[type] ?? '').split(',')).filter(Boolean).join(','));
const maximumMegabytes = computed(() => Math.round((props.maxSizeBytes / 1024 / 1024) * 10) / 10);

async function showError(message) {
    error.value = message;
    await nextTick();
    fileInput.value?.focus();
}

function upload(event) {
    const file = event.target.files?.[0];

    if (!file) {
        return;
    }

    error.value = '';
    progress.value = 0;
    isUploading.value = true;
    uploadedAttachment.value = null;

    const body = new FormData();
    body.append('attachable_type', props.attachableType);
    body.append('attachable_id', String(props.attachableId));
    body.append('file', file);

    const request = new XMLHttpRequest();
    request.open('POST', '/internal/v1/attachments');
    request.setRequestHeader('Accept', 'application/json');
    request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (csrfToken) {
        request.setRequestHeader('X-CSRF-TOKEN', csrfToken);
    }

    request.upload.addEventListener('progress', (uploadProgress) => {
        if (uploadProgress.lengthComputable) {
            progress.value = Math.round((uploadProgress.loaded / uploadProgress.total) * 100);
        }
    });
    request.addEventListener('load', () => {
        isUploading.value = false;

        try {
            const response = JSON.parse(request.responseText);

            if (request.status >= 200 && request.status < 300) {
                progress.value = 100;
                uploadedAttachment.value = response.attachment;
                emit('uploaded', response.attachment);

                return;
            }

            showError(response.errors?.file?.[0] ?? response.message ?? 'Le téléversement a échoué. Réessayez.');
        } catch {
            showError('Le téléversement a échoué. Réessayez.');
        }
    });
    request.addEventListener('error', () => {
        isUploading.value = false;
        showError('La connexion a interrompu le téléversement. Réessayez.');
    });
    request.send(body);
}
</script>

<template>
    <div class="grid min-w-0 gap-3">
        <label for="attachment-file" class="font-semibold text-ink">Ajouter une pièce jointe</label>
        <input
            id="attachment-file"
            ref="fileInput"
            type="file"
            :accept="accept"
            capture="environment"
            :aria-describedby="error ? 'attachment-file-error' : 'attachment-file-help'"
            :aria-invalid="error ? 'true' : 'false'"
            class="min-h-12 min-w-0 max-w-full rounded-lg border border-separator bg-surface p-2 text-base file:mr-3 file:min-h-11 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:text-white"
            @change="upload"
        >
        <p id="attachment-file-help" class="text-sm text-ink-secondary">
            PDF ou image autorisée, {{ maximumMegabytes }} Mo maximum. Vous pouvez utiliser l’appareil photo.
        </p>
        <div v-if="isUploading || progress === 100" class="grid gap-1" aria-live="polite">
            <label for="attachment-progress" class="text-sm font-semibold">Téléversement : {{ progress }} %</label>
            <progress id="attachment-progress" :value="progress" max="100" class="h-3 w-full">{{ progress }} %</progress>
        </div>
        <p v-if="error" id="attachment-file-error" class="text-sm font-semibold text-danger">⚠ {{ error }}</p>
        <div v-if="uploadedAttachment" class="flex min-w-0 items-center gap-3 rounded-lg border border-separator p-3">
            <img
                v-if="uploadedAttachment.thumbnail_url"
                :src="uploadedAttachment.thumbnail_url"
                alt=""
                width="64"
                height="64"
                class="size-16 shrink-0 rounded object-cover"
            >
            <p class="min-w-0 break-words text-sm font-semibold">{{ uploadedAttachment.original_name }}</p>
        </div>
    </div>
</template>
