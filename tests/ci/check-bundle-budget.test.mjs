import test from 'node:test';
import assert from 'node:assert/strict';

import { collectAssets, isWithinBudget } from './check-bundle-budget.mjs';

test('le budget accepte une taille inférieure ou égale à la limite', () => {
    assert.equal(isWithinBudget(299 * 1024, 300 * 1024), true);
    assert.equal(isWithinBudget(300 * 1024, 300 * 1024), true);
});

test('le budget refuse le premier octet au-dessus de la limite', () => {
    assert.equal(isWithinBudget(300 * 1024 + 1, 300 * 1024), false);
});

test('les ressources communes et celles de la page sont dédupliquées', () => {
    const manifest = {
        'resources/js/app.js': {
            file: 'assets/app.js',
            css: ['assets/app.css'],
        },
        'resources/js/Pages/Platform/Demo.vue': {
            file: 'assets/demo.js',
            imports: ['resources/js/app.js'],
        },
    };

    assert.deepEqual(
        collectAssets(manifest, [
            'resources/js/app.js',
            'resources/js/Pages/Platform/Demo.vue',
        ]),
        ['assets/app.css', 'assets/app.js', 'assets/demo.js'],
    );
});
