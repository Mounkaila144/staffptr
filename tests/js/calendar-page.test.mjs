import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('../../resources/js/Pages/Platform/Calendar/Index.vue', import.meta.url), 'utf8');

test('calendar remains compact at 320px and labels every non-working day', () => {
    assert.match(source, /grid-cols-7/);
    assert.match(source, /min-w-0/);
    assert.doesNotMatch(source, /min-w-\[/);
    assert.match(source, /day\.status_label/);
    assert.match(source, /Chaque fermeture est indiquée par un libellé/);
    assert.match(source, /Ajouter un jour férié/);
});
