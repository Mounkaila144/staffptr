import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const homeSource = await readFile(new URL('../../resources/js/Pages/Identity/Home.vue', import.meta.url), 'utf8');
const decisionSource = await readFile(new URL('../../resources/js/Pages/Finance/Expenses/Approvals/Index.vue', import.meta.url), 'utf8');

test('AC 1 et 6 — le bloc prioritaire et son état vide exact précèdent le reste de l’accueil', () => {
    const approvalIndex = homeSource.indexOf('data-testid="approval-queue-block"');
    const welcomeIndex = homeSource.indexOf('id="home-title"');

    assert.ok(approvalIndex >= 0);
    assert.ok(welcomeIndex > approvalIndex);
    assert.match(homeSource, /En attente de mon approbation/);
    assert.match(homeSource, /approvalQueue\.empty_message/);
    assert.match(homeSource, /Voir les dépenses traitées/);
});

test('AC 3 et 7 — la décision directe reste mobile, partielle et affiche le justificatif en place', () => {
    assert.match(decisionSource, /return_to.*decision/);
    assert.match(decisionSource, /only: \['expenses', 'readiness', 'view', 'focusedExpenseId', 'success'\]/);
    assert.match(decisionSource, /Consulter le justificatif sur cet écran/);
    assert.match(decisionSource, /loading="lazy"/);
    assert.match(decisionSource, /expense\.attachment\.inline_url/);
    assert.match(decisionSource, /min-w-0/);
    assert.doesNotMatch(decisionSource, /min-w-\[[3-9][0-9]{2}px\]|w-\[[3-9][0-9]{2}px\]/);
});

test('AC 7 — toutes les actions de décision utilisent les cibles tactiles du système interne', () => {
    assert.match(decisionSource, /<AppButton/);
    assert.match(decisionSource, /touch-target/);
    assert.doesNotMatch(decisionSource, /https?:\/\//);
});
