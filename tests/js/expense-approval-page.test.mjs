import assert from 'node:assert/strict';
import fs from 'node:fs';

const page = fs.readFileSync('resources/js/Pages/Finance/Expenses/Approvals/Index.vue', 'utf8');
const navigation = fs.readFileSync('resources/js/Composables/usePermissions.js', 'utf8');
const queueService = fs.readFileSync('app/Services/Finance/ExpenseApprovalQueueService.php', 'utf8');

assert.match(page, /Chaque dépense exige les décisions de deux comptes de direction distincts/u);
assert.match(page, /readiness\.message/u, 'Le motif d’indisponibilité doit être rendu explicitement.');
assert.match(page, /expense\.requester_message/u, 'Le message demandeur doit rester visible sur la carte.');
assert.match(page, /expense\.approval_progress/u, 'La progression textuelle doit être rendue sur chaque carte.');
assert.match(queueService, /approbation.*sur deux/u, 'La progression doit annoncer le nombre de décisions attendues.');
assert.match(page, /touch|AppButton/u, 'Les décisions doivent utiliser les cibles tactiles du design system.');
assert.match(page, /min-w-0/u, 'La page doit pouvoir se contracter à 320 px sans défilement horizontal.');
assert.match(page, /maxlength="255"/u, 'Le motif doit respecter la capacité du schéma.');
assert.match(navigation, /approvals: '\/depenses\/approbations'/u, 'La navigation direction doit ouvrir la vraie route.');

console.log('expense-approval-page.test.mjs: OK');
