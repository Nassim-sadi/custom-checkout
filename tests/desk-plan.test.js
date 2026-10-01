/**
 * Unit tests for the stop desk resolution in assets/js/cca-desks.js.
 *
 * Dependency-free on purpose: `node tests/desk-plan.test.js`. The fixture is the
 * real Batna (wilaya 5) payload, where Yalidine files
 * "Agence du CHU Route de Tazoult" under commune Batna (501) while Tazoult
 * (508) has no desk at all. That mismatch is the case this guards against.
 */
'use strict';

const assert = require('node:assert');
const { planDesks } = require('../assets/js/cca-desks.js');

// --- fixtures ---------------------------------------------------------------

const BATNA = [
  { id: 50101, name: 'Agence des 500 Logements',       commune_id: 501, commune_name: 'Batna',   address: 'Cité des 500 Logements' },
  { id: 50103, name: 'Agence du CHU Route de Tazoult', commune_id: 501, commune_name: 'Batna',   address: 'Route de Tazoult' },
  { id: 50401, name: 'Agence Merouana',                commune_id: 504, commune_name: 'Merouana', address: '' },
  { id: 54201, name: 'Agence de Barika',               commune_id: 542, commune_name: 'Barika',  address: '' },
];

const ELMENIA = [
  { id: 580601, name: 'Agence de El Menia', commune_id: 5806, commune_name: 'El Menia', address: '' },
];

const ids = (plan) => plan.offered.map((d) => d.id);

// --- cases ------------------------------------------------------------------

const tests = {
  'single desk in the commune is auto-selected': () => {
    const plan = planDesks(ELMENIA, 5806, 0);
    assert.strictEqual(plan.mode, 'commune');
    assert.deepStrictEqual(ids(plan), [580601]);
    assert.strictEqual(plan.selected, 580601);
    assert.strictEqual(plan.selectedName, 'Agence de El Menia');
  },

  'multiple desks in the commune offer only those': () => {
    const plan = planDesks(BATNA, 501, 0);
    assert.strictEqual(plan.mode, 'commune');
    assert.deepStrictEqual(ids(plan), [50101, 50103]);
    // More than one on offer => the customer chooses.
    assert.strictEqual(plan.selected, 0);
  },

  'commune with no desk falls back to every desk in the wilaya': () => {
    const plan = planDesks(BATNA, 508, 0);
    assert.strictEqual(plan.mode, 'fallback');
    assert.deepStrictEqual(ids(plan), [50101, 50103, 50401, 54201]);
    assert.strictEqual(plan.selected, 0, 'must not preselect across communes when there is a choice');
  },

  'fallback with exactly one desk takes the uniform auto-select rule': () => {
    // Wilaya 58 has a single desk in commune 5806; commune 5808 has none.
    const plan = planDesks(ELMENIA, 5808, 0);
    assert.strictEqual(plan.mode, 'fallback');
    assert.deepStrictEqual(ids(plan), [580601]);
    assert.strictEqual(plan.selected, 580601);
  },

  'no desks anywhere in the wilaya': () => {
    const plan = planDesks([], 501, 0);
    assert.strictEqual(plan.mode, 'wilaya-empty');
    assert.deepStrictEqual(plan.offered, []);
    assert.strictEqual(plan.selected, 0);
  },

  'a desk restored from the session wins when still on offer': () => {
    const plan = planDesks(BATNA, 501, 50103);
    assert.strictEqual(plan.mode, 'commune');
    assert.strictEqual(plan.selected, 50103);
  },

  'a restored desk survives the commune -> wilaya fallback': () => {
    const plan = planDesks(BATNA, 508, 50103);
    assert.strictEqual(plan.mode, 'fallback');
    assert.strictEqual(plan.selected, 50103);
  },

  'a stale restored desk is ignored and falls through to the single-desk rule': () => {
    const plan = planDesks(ELMENIA, 5806, 99999);
    assert.strictEqual(plan.selected, 580601, 'stale id must not win, but the only desk is taken');
  },

  'a stale restored desk is dropped when nothing can be auto-selected': () => {
    const plan = planDesks(BATNA, 501, 54201);
    assert.strictEqual(plan.mode, 'commune');
    assert.deepStrictEqual(ids(plan), [50101, 50103]);
    assert.strictEqual(plan.selected, 0, 'a desk outside the commune must not be restored here');
  },

  'desks with no commune never count as being in the commune': () => {
    const orphan = [{ id: 1, name: 'Orphan', commune_id: 0, commune_name: '' }];
    const plan = planDesks(orphan, 0, 0);
    assert.strictEqual(plan.mode, 'fallback', 'commune_id 0 cannot match the selected commune');
    assert.strictEqual(plan.selected, 1);
  },

  'ids arriving as numeric strings still match': () => {
    const asStrings = [{ id: '50103', name: 'Tazoult desk', commune_id: '501', commune_name: 'Batna' }];
    const plan = planDesks(asStrings, '501', '50103');
    assert.strictEqual(plan.mode, 'commune');
    assert.strictEqual(plan.selected, 50103);
  },

  'no commune selected yet offers nothing silently in-commune': () => {
    const plan = planDesks(BATNA, 0, 0);
    assert.strictEqual(plan.mode, 'fallback');
    assert.strictEqual(plan.selected, 0);
  },

  'non-array input degrades to the empty state': () => {
    assert.strictEqual(planDesks(null, 501, 0).mode, 'wilaya-empty');
    assert.strictEqual(planDesks(undefined, 501, 0).mode, 'wilaya-empty');
    assert.strictEqual(planDesks('nope', 501, 0).mode, 'wilaya-empty');
  },
};

// --- runner -----------------------------------------------------------------

let failed = 0;
const names = Object.keys(tests);
for (const name of names) {
  try {
    tests[name]();
    console.log(`  ok   ${name}`);
  } catch (err) {
    failed++;
    console.log(`  FAIL ${name}`);
    console.log(`       ${err.message}`);
  }
}
console.log(`\n${names.length - failed}/${names.length} passed, ${failed} failed`);
process.exit(failed === 0 ? 0 : 1);
