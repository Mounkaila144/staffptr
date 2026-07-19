import { computed, isReactive, isRef, ref, unref, watch } from 'vue';

export const DRAFT_TTL_MS = 7 * 24 * 60 * 60 * 1000;
export const DRAFT_DEBOUNCE_MS = 2000;

const forbiddenDraftKey = /(attachment|file|proof|validated.?financial|financial.?validated)/i;

export function draftStorageKey(formKey, userId, entityId) {
    const safeUserId = userId || 'anonymous';
    const safeEntityId = entityId || 'new';

    return `draft:${safeUserId}:${formKey}:${safeEntityId}`;
}

export function sanitizeDraft(value) {
    if (value === null || typeof value !== 'object') {
        return value;
    }

    if ((typeof File !== 'undefined' && value instanceof File)
        || (typeof Blob !== 'undefined' && value instanceof Blob)) {
        return undefined;
    }

    if (Array.isArray(value)) {
        return value.map(sanitizeDraft).filter((item) => item !== undefined);
    }

    return Object.fromEntries(Object.entries(value)
        .filter(([key]) => !forbiddenDraftKey.test(key))
        .map(([key, item]) => [key, sanitizeDraft(item)])
        .filter(([, item]) => item !== undefined));
}

function browserStorage() {
    return typeof window === 'undefined' ? null : window.localStorage;
}

export function hasLocalDrafts(storage = browserStorage()) {
    if (storage === null) {
        return false;
    }

    for (let index = 0; index < storage.length; index += 1) {
        if (storage.key(index)?.startsWith('draft:')) {
            return true;
        }
    }

    return false;
}

export function useDraft(formKey, userId, entityId, formState = {}, options = {}) {
    const storage = options.storage ?? browserStorage();
    const now = options.now ?? (() => Date.now());
    const schedule = options.schedule ?? ((callback, delay) => window.setTimeout(callback, delay));
    const cancel = options.cancel ?? ((timer) => window.clearTimeout(timer));
    const key = draftStorageKey(formKey, userId, entityId);
    const savedAt = ref(null);
    const restoredAt = ref(null);
    const restored = ref(false);
    let timer = null;

    const purge = () => {
        if (timer !== null) {
            cancel(timer);
            timer = null;
        }

        storage?.removeItem(key);
        savedAt.value = null;
        restoredAt.value = null;
        restored.value = false;
    };

    const saveNow = (value = unref(formState)) => {
        if (storage === null) {
            return;
        }

        if (timer !== null) {
            cancel(timer);
            timer = null;
        }

        const timestamp = now();
        storage.setItem(key, JSON.stringify({
            savedAt: timestamp,
            value: sanitizeDraft(value),
        }));
        savedAt.value = timestamp;
    };

    const scheduleSave = (value = unref(formState)) => {
        if (timer !== null) {
            cancel(timer);
        }

        timer = schedule(() => {
            timer = null;
            saveNow(value);
        }, DRAFT_DEBOUNCE_MS);
    };

    const restore = () => {
        const raw = storage?.getItem(key);

        if (!raw) {
            return null;
        }

        try {
            const draft = JSON.parse(raw);

            if (!draft.savedAt || now() - draft.savedAt > DRAFT_TTL_MS) {
                purge();

                return null;
            }

            restored.value = true;
            restoredAt.value = draft.savedAt;
            savedAt.value = draft.savedAt;

            return draft.value;
        } catch {
            purge();

            return null;
        }
    };

    if (isRef(formState) || isReactive(formState)) {
        watch(formState, (value) => scheduleSave(value), { deep: true });
    }

    const formatTime = (timestamp) => timestamp
        ? new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(timestamp)
        : '';

    return {
        // `anonymous` is a temporary pre-authentication fallback; epic 2 must provide the real user id.
        hasStoredDrafts: computed(() => {
            savedAt.value;

            return hasLocalDrafts(storage);
        }),
        key,
        purge,
        restore,
        restored,
        restoredLabel: computed(() => restoredAt.value ? `Brouillon restauré (${formatTime(restoredAt.value)})` : ''),
        saveNow,
        savedAt,
        savedLabel: computed(() => savedAt.value ? `✓ Enregistré à ${formatTime(savedAt.value)}` : ''),
        scheduleSave,
    };
}
