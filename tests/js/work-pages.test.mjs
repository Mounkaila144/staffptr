import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
const home = read('resources/js/Pages/Identity/Home.vue');
const objectives = read('resources/js/Pages/Work/Objectives/Index.vue');
const calendar = read('resources/js/Pages/Work/Objectives/Calendar.vue');
const summary = read('resources/js/Pages/Work/Objectives/Summary.vue');
const projects = read('resources/js/Pages/Work/Projects/Index.vue');
const tasks = read('resources/js/Pages/Work/Tasks/Index.vue');

assert.ok(home.indexOf('approval-queue-block') < home.indexOf('monthly-objectives-title'));
assert.match(home, /workBlocks\.objectives/);
assert.match(home, /workBlocks\.todayTasks/);
assert.match(home, /workBlocks\.deadlines/);
assert.match(home, /workBlocks\.notifications/);
assert.match(objectives, /Preuve attendue/);
assert.match(objectives, /useDraft\(\s*["']objective["']/);
assert.match(calendar, /liste chronologique/);
assert.doesNotMatch(summary.toLowerCase(), /classement comparatif|rang|meilleur/);
for (const source of [objectives, calendar, summary, projects, tasks]) {
    assert.match(source, /min-w-0/);
    assert.doesNotMatch(source, /https?:\/\//);
}
console.log('work-pages.test.mjs: OK');
