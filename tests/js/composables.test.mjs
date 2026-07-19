import assert from 'node:assert/strict';
import test from 'node:test';
import { ref } from 'vue';
import {
    DRAFT_DEBOUNCE_MS,
    DRAFT_TTL_MS,
    draftStorageKey,
    sanitizeDraft,
    useDraft,
} from '../../resources/js/Composables/useDraft.js';
import { useMoney } from '../../resources/js/Composables/useMoney.js';
import {
    createPermissionChecker,
    usePermissions,
} from '../../resources/js/Composables/usePermissions.js';

function memoryStorage() {
    const values = new Map();

    return {
        get length() {
            return values.size;
        },
        getItem: (key) => values.get(key) ?? null,
        key: (index) => [...values.keys()][index] ?? null,
        removeItem: (key) => values.delete(key),
        setItem: (key, value) => values.set(key, value),
    };
}

test('AC 8 — useMoney formate des entiers XOF sans décimale', () => {
    const { formatAmount, formatMoney, parseMoney } = useMoney();

    assert.equal(formatMoney(45000), '45 000 F CFA');
    assert.equal(formatMoney(-1250), '-1 250 F CFA — sortie');
    assert.equal(formatAmount(0), '0');
    assert.equal(parseMoney('1 234,99'), 1234);
    assert.equal(parseMoney(Number.MAX_SAFE_INTEGER), Number.MAX_SAFE_INTEGER);
});

test('AC 8 — usePermissions masque l’interface sans prétendre autoriser le serveur', () => {
    const checker = createPermissionChecker(['reports.write']);

    assert.equal(checker.can('reports.write'), true);
    assert.equal(checker.can('finance.view'), false);
    assert.equal(checker.canAny(['finance.view', 'reports.write']), true);

    const permissions = ref([
        'role:direction',
        'role:finance',
        'navigation.home',
        'approvals.view',
        'team.view',
        'finance.view',
    ]);
    const navigation = usePermissions(permissions);

    assert.equal(navigation.activeRole.value, 'direction');
    assert.deepEqual(
        navigation.primaryNavigation.value.map((item) => item.label),
        ['Accueil', 'À approuver', 'Équipe', 'Argent'],
    );
    assert.ok(navigation.moreNavigation.value.includes('Mon rapport du jour'));
    assert.ok(navigation.moreNavigation.value.includes('Rapprochement'));
});

test('AC 8 — useDraft cloisonne les utilisateurs et enregistre après deux secondes', () => {
    const storage = memoryStorage();
    let pending = null;
    let delay = null;
    const draft = useDraft('daily-report', 7, 42, {}, {
        storage,
        now: () => 1_000,
        schedule: (callback, milliseconds) => {
            pending = callback;
            delay = milliseconds;

            return 1;
        },
        cancel: () => {},
    });

    assert.equal(draft.key, 'draft:7:daily-report:42');
    assert.notEqual(draft.key, draftStorageKey('daily-report', 8, 42));
    draft.scheduleSave({ work: 'Rapport rédigé' });
    assert.equal(storage.getItem(draft.key), null);
    assert.equal(delay, DRAFT_DEBOUNCE_MS);
    pending();
    assert.deepEqual(JSON.parse(storage.getItem(draft.key)).value, { work: 'Rapport rédigé' });
});

test('AC 8 — useDraft sauvegarde au flou, restaure et purge après sept jours', () => {
    const storage = memoryStorage();
    let currentTime = 50_000;
    const draft = useDraft('objective', 'fixture-user', 'new', {}, {
        storage,
        now: () => currentTime,
        schedule: () => 1,
        cancel: () => {},
    });

    draft.saveNow({ title: 'Déployer la fondation' });
    assert.deepEqual(draft.restore(), { title: 'Déployer la fondation' });
    assert.equal(draft.restored.value, true);

    currentTime += DRAFT_TTL_MS + 1;
    assert.equal(draft.restore(), null);
    assert.equal(storage.getItem(draft.key), null);
});

test('AC 8 — useDraft exclut les pièces jointes et données financières validées', () => {
    assert.deepEqual(sanitizeDraft({
        description: 'Demande préparée',
        attachment: 'preuve.pdf',
        files: ['preuve.pdf'],
        validatedFinancialData: { amount: 50000 },
        nested: { proof: 'photo.jpg', note: 'reste local' },
    }), {
        description: 'Demande préparée',
        nested: { note: 'reste local' },
    });
});
