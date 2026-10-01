/**
 * Stop desk resolution for Custom Checkout Algeria.
 *
 * Pure logic, no jQuery and no DOM, so the exact same file backs the browser
 * (via assets/js/checkout.js) and tests/desk-plan.test.js.
 *
 * Yalidine registers a desk under the commune it physically sits in, which is
 * not always the commune the customer lives in. A desk named
 * "Agence du CHU Route de Tazoult" is filed under commune Batna, so picking
 * Tazoult legitimately finds nothing. Instead of dead-ending the customer we
 * fall back to the wilaya's desks and label each one with its real commune.
 *
 * ccaPlanDesks(centers, communeId, restoreId) -> {
 *   mode: 'commune' | 'fallback' | 'wilaya-empty',
 *   offered: desk[],          // what the customer is allowed to pick
 *   selected: 0 | deskId,     // 0 = nothing preselected
 *   selectedName: string
 * }
 */
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root) root.ccaPlanDesks = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  function toId(value) {
    var n = parseInt(value, 10);
    return isNaN(n) ? 0 : n;
  }

  function planDesks(centers, communeId, restoreId) {
    var all = Array.isArray(centers) ? centers : [];
    var cid = toId(communeId);
    var i, j;

    // A desk with no commune of its own can never match, so it is never treated
    // as being in the customer's commune.
    var inCommune = [];
    for (i = 0; i < all.length; i++) {
      if (cid !== 0 && toId(all[i].commune_id) === cid) inCommune.push(all[i]);
    }

    var mode, offered;
    if (inCommune.length) {
      mode = 'commune';
      offered = inCommune;
    } else if (all.length) {
      mode = 'fallback';
      offered = all.slice();
    } else {
      mode = 'wilaya-empty';
      offered = [];
    }

    // A desk restored from the session wins, but only while it is still on
    // offer -- a stale id must never survive a wilaya or commune change.
    var selected = 0, selectedName = '';
    var restore = toId(restoreId);
    if (restore) {
      for (j = 0; j < offered.length; j++) {
        if (toId(offered[j].id) === restore) {
          selected = restore;
          selectedName = offered[j].name || '';
          break;
        }
      }
    }
    // Exactly one candidate means there is nothing to choose, so take it.
    if (!selected && offered.length === 1) {
      selected = toId(offered[0].id);
      selectedName = offered[0].name || '';
    }

    return {
      mode: mode,
      offered: offered,
      selected: selected,
      selectedName: selectedName
    };
  }

  return { planDesks: planDesks };
});
