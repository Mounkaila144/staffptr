import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const edit = readFileSync(new URL('../../resources/js/Pages/Accountability/DailyReports/Edit.vue', import.meta.url), 'utf8');
const reviews = readFileSync(new URL('../../resources/js/Pages/Accountability/DailyReports/Reviews/Index.vue', import.meta.url), 'utf8');
const analytics = readFileSync(new URL('../../resources/js/Pages/Accountability/DailyReports/Index.vue', import.meta.url), 'utf8');
const blockers = readFileSync(new URL('../../resources/js/Pages/Accountability/Blockers/Index.vue', import.meta.url), 'utf8');
const home = readFileSync(new URL('../../resources/js/Pages/Identity/Home.vue', import.meta.url), 'utf8');

test('Story 6.1 — le rapport restaure le brouillon et explique la coupure sans promettre de synchronisation', () => {
    assert.match(edit, /useDraft\(\s*['"]daily-report['"]/);
    assert.match(edit, /L'envoi n'a pas abouti — pas de connexion\. Votre rapport est conservé sur cet appareil\./);
    assert.match(edit, /saveNow\(\)/);
    assert.match(edit, /purge\(\)/);
    assert.match(edit, /DRAFT|attachment_ulid|attachmentUlid/);
});

test('Story 6.1 — les six réponses, la demande de tâche et le blocage restent sur la même page mobile', () => {
    for (const field of ['planned_task', 'achieved_result', 'evidence_link', 'blocker_present', 'next_action', 'help_requested']) {
        assert.match(edit, new RegExp(field));
    }
    assert.match(edit, /Demander une tâche/);
    assert.match(edit, /Signaler sans quitter le rapport/);
    assert.match(edit, /touch-target/);
    assert.match(edit, /min-w-0/);
});

test('Story 6.1 — le dashboard place le rapport avant la file sauf pour direction', () => {
    const dailyPosition = home.indexOf('dailyReport && !dailyReport.after_approval');
    const approvalPosition = home.indexOf('v-if="approvalQueue"');
    const directionPosition = home.indexOf('dailyReport && dailyReport.after_approval');
    assert.ok(dailyPosition > 0 && dailyPosition < approvalPosition);
    assert.ok(directionPosition > approvalPosition);
    assert.match(home, /blockerBlock\.empty_message/);
});

test('Story 6.1 — validation, vues et blocages exposent leurs états positifs', () => {
    assert.match(reviews, /Valider/);
    assert.match(reviews, /Retourner avec un motif/);
    assert.match(reviews, /Comparer les/);
    assert.match(analytics, /Tous les rapports du jour ont été envoyés\./);
    assert.match(blockers, /Aucun blocage ouvert\./);
    assert.match(blockers, /Fermer sans solution/);
});
