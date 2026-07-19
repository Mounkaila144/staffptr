import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');
const componentSources = [
    'ActionCard.vue',
    'AppButton.vue',
    'EmptyState.vue',
    'FormField.vue',
    'LoadingSkeleton.vue',
    'OfflineBanner.vue',
    'ProcessingQueue.vue',
    'SensitiveConfirmation.vue',
    'StatusBadge.vue',
].map((file) => readFileSync(new URL(`../../resources/js/Components/${file}`, import.meta.url), 'utf8'));

test('AC 7 — la charte conserve toutes les couleurs mesurées', () => {
    for (const color of [
        '#1b5faf', '#1a1a19', '#54534f', '#6b6a66', '#0b6b34', '#8a5200', '#b3261e',
        '#4a4a48', '#ffffff', '#f7f7f5', '#e3e2de', '#e6f4ea', '#fdf1dc', '#fbe9e7',
        '#efefed', '#e4eefa',
    ]) {
        assert.match(css, new RegExp(color));
    }
    assert.match(css, /system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif/);
});

test('AC 6 — les composants ne produisent aucun style en ligne', () => {
    for (const source of componentSources) {
        assert.doesNotMatch(source, /<style\b|\sstyle=|:style=/i);
    }
});
