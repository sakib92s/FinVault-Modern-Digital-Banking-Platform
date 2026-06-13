/**
 * FinVault - core frontend behaviour:
 * theme toggle, sidebar, toasts, global live search, beneficiary autocomplete.
 */
(function () {
  'use strict';

  /* ---------- Theme (dark / light, persisted) ---------- */
  const root = document.documentElement;
  root.dataset.theme = localStorage.getItem('fv-theme') || root.dataset.theme || 'light';
  const themeBtn = document.getElementById('themeToggle');
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
      localStorage.setItem('fv-theme', root.dataset.theme);
    });
  }

  /* ---------- Sidebar toggle (mobile) ---------- */
  const sidebar = document.getElementById('sidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  if (sidebar && sidebarToggle) {
    sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
  }

  /* ---------- Toast notifications ---------- */
  const host = document.getElementById('toastHost');
  window.fvToast = function (type, msg) {
    if (!host) return;
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.textContent = msg;
    host.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 400); }, 4200);
  };
  document.querySelectorAll('.toast-seed').forEach(s => fvToast(s.dataset.type, s.dataset.msg));

  /* ---------- Helpers ---------- */
  function debounce(fn, ms) {
    let t;
    return function () { clearTimeout(t); t = setTimeout(() => fn.apply(this, arguments), ms); };
  }
  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  /* ---------- Smart global search ---------- */
  const gs = document.getElementById('globalSearch');
  const gsResults = document.getElementById('globalSearchResults');
  if (gs && gsResults) {
    const run = debounce(() => {
      const q = gs.value.trim();
      if (q.length < 2) { gsResults.classList.remove('open'); gsResults.innerHTML = ''; return; }
      gsResults.innerHTML = '<div class="skeleton-row"></div><div class="skeleton-row"></div>';
      gsResults.classList.add('open');
      fetch(window.FV.base + '/api/search.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(d => {
          const items = d.results || [];
          if (!items.length) { gsResults.innerHTML = '<div class="sr-empty">No results</div>'; return; }
          let html = '', last = '';
          items.forEach(it => {
            if (it.category !== last) { html += '<div class="sr-cat">' + esc(it.category) + '</div>'; last = it.category; }
            html += '<a class="sr-item" href="' + esc(it.url) + '"><strong>' + esc(it.label)
                  + '</strong><small>' + esc(it.sub) + '</small></a>';
          });
          gsResults.innerHTML = html;
        })
        .catch(() => { gsResults.innerHTML = '<div class="sr-empty">Search unavailable</div>'; });
    }, 250);
    gs.addEventListener('input', run);
    document.addEventListener('click', e => {
      if (!gs.parentElement.contains(e.target)) gsResults.classList.remove('open');
    });
  }

  /* ---------- Beneficiary autocomplete ---------- */
  const bs = document.getElementById('benSearch');
  const bsResults = document.getElementById('benResults');
  if (bs && bsResults) {
    const fill = (s) => {
      const set = (id, v) => { const el = document.getElementById(id); if (el && v) el.value = v; };
      set('benName', s.name); set('benAccount', s.account_number);
      set('benEmail', s.email); set('benMobile', s.mobile);
      bs.value = s.name + ' \u00b7 ' + s.account_number;
      bsResults.classList.remove('open');
    };
    const run = debounce(() => {
      const q = bs.value.trim();
      if (q.length < 2) { bsResults.classList.remove('open'); return; }
      fetch(window.FV.base + '/api/autocomplete.php?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(d => {
          const list = d.suggestions || [];
          if (!list.length) { bsResults.innerHTML = '<div class="sr-empty">No matches</div>'; bsResults.classList.add('open'); return; }
          bsResults.innerHTML = list.map((s, i) =>
            '<button type="button" class="sr-item" data-i="' + i + '"><strong>' + esc(s.name)
            + '</strong><small>' + esc(s.account_number) + (s.mobile ? ' \u00b7 ' + esc(s.mobile) : '')
            + (s.source === 'saved' ? ' \u00b7 saved payee' : '') + '</small></button>').join('');
          bsResults.classList.add('open');
          bsResults.querySelectorAll('.sr-item').forEach(btn =>
            btn.addEventListener('click', () => fill(list[+btn.dataset.i])));
        })
        .catch(() => {});
    }, 250);
    bs.addEventListener('input', run);
    document.addEventListener('click', e => {
      if (!bs.parentElement.contains(e.target)) bsResults.classList.remove('open');
    });
  }

  /* ---------- Quick-pick saved beneficiaries on transfer page ---------- */
  document.querySelectorAll('.ben-pick').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const acc = document.getElementById('benAccount');
      if (acc) acc.value = btn.dataset.account;
      if (bs) bs.value = btn.dataset.name + ' \u00b7 ' + btn.dataset.account;
      fvToast('success', 'Beneficiary selected: ' + btn.dataset.name);
    });
  });
})();
