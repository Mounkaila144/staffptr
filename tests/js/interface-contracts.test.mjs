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

function token(name) {
    const match = css.match(new RegExp(`--color-${name}:\\s*(#[0-9a-f]{6})`, 'i'));
    assert.ok(match, `La charte doit déclarer --color-${name}.`);

    return match[1];
}

function contrast(foreground, background) {
    const luminance = (hex) => {
        const [r, g, b] = [1, 3, 5].map((offset) => Number.parseInt(hex.slice(offset, offset + 2), 16) / 255)
            .map((channel) => (channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4));

        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    const [light, dark] = [luminance(foreground), luminance(background)].sort((a, b) => b - a);

    return (light + 0.05) / (dark + 0.05);
}

test('AC 7 — chaque couple texte / fond de la charte atteint le contraste AA de 4,5:1', () => {
    // Le bleu de marque est celui du cahier d'interface : il ne change pas avec la refonte.
    assert.equal(token('primary').toLowerCase(), '#1b5faf');

    const pairs = [
        ['ink', 'surface'], ['ink', 'canvas'], ['ink-secondary', 'surface'], ['ink-secondary', 'canvas'],
        ['ink-muted', 'surface'], ['ink-muted', 'canvas'], ['primary', 'surface'], ['primary', 'primary-soft'],
        ['surface', 'primary'], ['surface', 'primary-strong'], ['success', 'success-soft'], ['warning', 'warning-soft'],
        ['danger', 'danger-soft'], ['neutral', 'neutral-soft'], ['rail-ink', 'rail'], ['rail-muted', 'rail'],
        ['surface', 'rail-active'],
    ];

    for (const [foreground, background] of pairs) {
        const ratio = contrast(token(foreground), token(background));
        assert.ok(ratio >= 4.5, `${foreground} sur ${background} : ${ratio.toFixed(2)}:1, sous le seuil AA.`);
    }

    assert.match(css, /system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif/);
});

test('AC 6 — les composants ne produisent aucun style en ligne', () => {
    for (const source of componentSources) {
        assert.doesNotMatch(source, /<style\b|\sstyle=|:style=/i);
    }
});
