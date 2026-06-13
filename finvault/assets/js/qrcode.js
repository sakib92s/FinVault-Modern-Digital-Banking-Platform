/**
 * FinVault QR payments (simulation).
 * - Renders a receive QR with {account, name, amount} JSON payload.
 * - "Fill from QR data" parses a pasted payload into the transfer form.
 * Requires qrcodejs (loaded via CDN on the transfer page).
 */
(function () {
  'use strict';

  const box = document.getElementById('myQr');
  const amountInput = document.getElementById('qrAmount');

  function render() {
    if (!box || typeof QRCode === 'undefined') return;
    box.innerHTML = '';
    const payload = JSON.stringify({
      app: 'FinVault',
      account: box.dataset.account,
      name: box.dataset.name,
      amount: amountInput && amountInput.value ? parseFloat(amountInput.value) : null
    });
    new QRCode(box, { text: payload, width: 168, height: 168, correctLevel: QRCode.CorrectLevel.M });
  }
  if (box) {
    render();
    if (amountInput) amountInput.addEventListener('input', render);
  }

  const fillBtn = document.getElementById('qrFill');
  if (fillBtn) {
    fillBtn.addEventListener('click', () => {
      try {
        const data = JSON.parse(document.getElementById('qrPayload').value);
        const acc = document.getElementById('benAccount');
        const amt = document.getElementById('benAmount');
        if (acc && data.account) acc.value = data.account;
        if (amt && data.amount) amt.value = data.amount;
        window.fvToast && fvToast('success', 'QR data applied' + (data.name ? ' \u00b7 ' + data.name : ''));
      } catch (e) {
        window.fvToast && fvToast('error', 'Invalid QR data. Paste the JSON payload from a FinVault QR.');
      }
    });
  }
})();
