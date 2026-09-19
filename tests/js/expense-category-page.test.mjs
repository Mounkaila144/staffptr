import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../resources/js/Pages/Finance/ExpenseCategories/Index.vue', import.meta.url), 'utf8');

assert.match(source, /Aucune catégorie de dépense\./u, "L'état vide doit être explicite.");
assert.match(source, /Dépense essentielle/u, 'Le marqueur essentiel doit avoir un libellé visible.');
assert.match(source, /Dépense non essentielle/u, 'La couleur ne doit pas être le seul canal.');
assert.match(source, /touch-target/u, 'Les actions doivent rester utilisables au pouce.');
assert.match(source, /min-w-0/u, "L'écran doit résister à une largeur de 320 px.");
assert.doesNotMatch(source, /overflow-x-/u, "L'écran ne doit pas introduire de défilement horizontal.");

console.log('expense-category-page.test.mjs: OK');
