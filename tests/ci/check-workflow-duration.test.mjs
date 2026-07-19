import test from 'node:test';
import assert from 'node:assert/strict';

import { isWithinDuration } from './check-workflow-duration.mjs';

test('la chaîne respecte une limite strictement inférieure à dix minutes', () => {
    assert.equal(isWithinDuration(599, 600), true);
    assert.equal(isWithinDuration(600, 600), false);
    assert.equal(isWithinDuration(601, 600), false);
});
