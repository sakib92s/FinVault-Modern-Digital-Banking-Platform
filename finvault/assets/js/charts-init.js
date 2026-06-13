/**
 * FinVault analytics - Chart.js initialisation.
 * Fetches series from /api/analytics.php and renders 10 charts
 * (line, area, bar, doughnut and pie types).
 */
(function () {
  'use strict';
  if (typeof Chart === 'undefined') return;

  const palette = ['#2563eb', '#7c3aed', '#059669', '#f59e0b', '#dc2626', '#0891b2', '#db2777', '#65a30d'];
  const grid = { color: 'rgba(148,163,184,.15)' };

  Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
  Chart.defaults.plugins.legend.position = 'bottom';

  function line(id, labels, data, label, fill) {
    const el = document.getElementById(id);
    if (!el) return;
    new Chart(el, {
      type: 'line',
      data: { labels, datasets: [{ label, data, borderColor: palette[0],
        backgroundColor: 'rgba(37,99,235,.15)', fill: !!fill, tension: .35, pointRadius: 2 }] },
      options: { scales: { x: { grid }, y: { grid, beginAtZero: true } } }
    });
  }

  function bar(id, labels, data, label, color) {
    const el = document.getElementById(id);
    if (!el) return;
    new Chart(el, {
      type: 'bar',
      data: { labels, datasets: [{ label, data, backgroundColor: color || palette[1], borderRadius: 6 }] },
      options: { scales: { x: { grid }, y: { grid, beginAtZero: true } } }
    });
  }

  function round(id, type, obj, colors) {
    const el = document.getElementById(id);
    if (!el) return;
    new Chart(el, {
      type,
      data: { labels: Object.keys(obj), datasets: [{ data: Object.values(obj),
        backgroundColor: colors || palette, borderWidth: 0 }] }
    });
  }

  fetch(window.FV.base + '/api/analytics.php?' + (window.FV_ANALYTICS_QS || ''), {
    headers: { 'X-CSRF-Token': window.FV.csrf }
  })
    .then(r => r.json())
    .then(d => {
      if (d.error) return;
      line('chUserGrowth', d.labels, d.userGrowth, 'Total users', true);          // area
      bar('chRegistrations', d.labels, d.registrations, 'New registrations');      // bar
      line('chTxns', d.labels, d.txnCounts, 'Transactions', false);                // line
      line('chVolume', d.labels, d.volume, 'Transfer volume (\u20b9)', true);      // area
      bar('chLogins', d.labels, d.logins, 'Logins', palette[5]);                   // bar
      line('chRevenue', d.labels, d.revenue, 'Simulated revenue (\u20b9)', true);  // area
      round('chLoans', 'doughnut', d.loans, ['#f59e0b', '#059669', '#dc2626']);    // doughnut
      round('chKyc', 'pie', d.kyc, ['#f59e0b', '#059669', '#dc2626', '#0891b2']); // pie
      round('chCards', 'doughnut', d.cards, ['#f59e0b', '#059669', '#64748b', '#dc2626']);
      bar('chGeo', Object.keys(d.geo), Object.values(d.geo), 'Users by state', palette[2]);
    })
    .catch(() => { /* charts silently skip when API unavailable */ });
})();
