import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const index = readFileSync(new URL('../../resources/js/Pages/Identity/Absences/Index.vue', import.meta.url), 'utf8');
const show = readFileSync(new URL('../../resources/js/Pages/Identity/Absences/Show.vue', import.meta.url), 'utf8');

assert.match(index, /variant="date"/u, 'Le formulaire doit utiliser des sélecteurs de date natifs.');
assert.match(index, /AttachmentUploader/u, 'Le justificatif facultatif doit être proposé.');
assert.match(index, /Aucune absence déclarée\./u, 'La zone personnelle doit avoir son état vide.');
assert.match(index, /Aucune demande à approuver\./u, "La zone d'approbation doit avoir son état vide.");
assert.match(index, /min-w-0/u, "L'écran doit résister aux contenus étroits à 320 px.");
assert.match(index, /Motif du refus/u, 'Le refus doit demander un motif visible.');
assert.match(show, /Ouvrir le justificatif/u, 'Le détail doit exposer le justificatif autorisé.');

console.log('absence-page.test.mjs: OK');
