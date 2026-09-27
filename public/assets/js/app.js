/**
 * Safe Trade — client-side behaviour.
 * Kept deliberately small: countdowns, light form help, flash dismiss.
 * All rules are re-checked server-side; nothing here is a security boundary.
 */

// ---- auction countdowns -------------------------------------------------
// Any element with [data-ends-at="YYYY-MM-DD HH:MM:SS"] becomes a live countdown.
function tickCountdowns() {
  document.querySelectorAll('[data-ends-at]').forEach(function (el) {
    var end = new Date(el.dataset.endsAt.replace(' ', 'T'));
    var diff = end - Date.now();
    if (isNaN(end)) return;
    if (diff <= 0) {
      el.textContent = 'Ended';
      return;
    }
    var d = Math.floor(diff / 864e5);
    var h = Math.floor(diff % 864e5 / 36e5);
    var m = Math.floor(diff % 36e5 / 6e4);
    var s = Math.floor(diff % 6e4 / 1e3);
    el.textContent = (d ? d + 'd ' : '') + h + 'h ' + m + 'm ' + s + 's';
  });
}
tickCountdowns();
setInterval(tickCountdowns, 1000);

// ---- bid form: client-side floor hint ----------------------------------
// Server enforces the real rule; this just saves a round trip.
var bidForm = document.querySelector('[data-bid-form]');
if (bidForm) {
  bidForm.addEventListener('submit', function (e) {
    var input = bidForm.querySelector('input[name="amount"]');
    var floor = parseInt(bidForm.dataset.minBid, 10) || 0;
    if (parseInt(input.value, 10) <= floor) {
      e.preventDefault();
      input.setCustomValidity('Bid must be more than the current high bid (€' + floor.toLocaleString() + ').');
      input.reportValidity();
      setTimeout(function () { input.setCustomValidity(''); }, 2500);
    }
  });
}

// ---- safety check-in: quick "expected back" buttons ---------------------
document.querySelectorAll('[data-add-hours]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = document.querySelector('input[name="expected_back"]');
    if (!input) return;
    var t = new Date(Date.now() + parseInt(btn.dataset.addHours, 10) * 36e5);
    t.setMinutes(t.getMinutes() - t.getTimezoneOffset()); // to local ISO
    input.value = t.toISOString().slice(0, 16);
  });
});

// ---- document form: suggest a title from the chosen type ----------------
var docType = document.querySelector('select[name="doc_type"]');
if (docType) {
  docType.addEventListener('change', function () {
    var title = document.querySelector('input[name="title"]');
    if (title && !title.value) {
      title.value = docType.options[docType.selectedIndex].text;
    }
  });
}

// ---- search: model list follows the chosen make -------------------------
// The make select points at its model select via [data-make-select="<id>"];
// the model select carries the whole {make: [models]} map in [data-models].
// Server-side filtering does not depend on this — it just trims the options.
document.querySelectorAll('[data-make-select]').forEach(function (makeSel) {
  var modelSel = document.getElementById(makeSel.dataset.makeSelect);
  if (!modelSel) return;

  var map = {};
  try { map = JSON.parse(modelSel.dataset.models || '{}'); } catch (err) { return; }

  var anyLabel = modelSel.options[0] ? modelSel.options[0].textContent : 'Any model';

  function allModels() {
    var seen = [];
    Object.keys(map).forEach(function (mk) {
      map[mk].forEach(function (mo) { if (seen.indexOf(mo) === -1) seen.push(mo); });
    });
    return seen.sort();
  }

  function fill(list, keep) {
    modelSel.innerHTML = '';
    modelSel.appendChild(new Option(anyLabel, ''));
    list.forEach(function (mo) { modelSel.appendChild(new Option(mo, mo)); });
    modelSel.value = list.indexOf(keep) === -1 ? '' : keep;
  }

  // Pages that render the options server-side (browse) already have them.
  if (modelSel.options.length <= 1) {
    fill(makeSel.value ? (map[makeSel.value] || []) : allModels(), '');
  }

  makeSel.addEventListener('change', function () {
    fill(makeSel.value ? (map[makeSel.value] || []) : allModels(), modelSel.value);
  });
});

// ---- flashes: click to dismiss ------------------------------------------
document.querySelectorAll('.flash').forEach(function (f) {
  f.style.cursor = 'pointer';
  f.title = 'Dismiss';
  f.addEventListener('click', function () { f.remove(); });
});
