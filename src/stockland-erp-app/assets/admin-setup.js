(function () {
  'use strict';
  const cfg = window.StocklandAdminSetup || {};
  const button = document.getElementById('stockland-run-full-setup');
  const statusNode = document.getElementById(cfg.statusTarget || 'stockland-admin-setup-status');
  if (!button || !statusNode || !cfg.restUrl || !cfg.nonce) return;

  const setStatus = (message, isError) => {
    statusNode.textContent = String(message || '');
    statusNode.style.color = isError ? '#b32d2e' : '#1d5b37';
    statusNode.style.fontWeight = '600';
    statusNode.style.marginTop = '8px';
  };
  const request = async (path, options) => {
    const opts = options || {};
    const headers = Object.assign({ 'X-WP-Nonce': cfg.nonce, 'Accept': 'application/json' }, opts.headers || {});
    if (opts.method && opts.method !== 'GET') headers['Content-Type'] = 'application/json';
    const response = await fetch(cfg.restUrl.replace(/\/$/, '') + '/' + path, Object.assign({
      method: opts.method || 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: headers
    }, opts));
    let payload;
    try { payload = await response.json(); } catch (_) { throw new Error('The setup endpoint returned an unreadable response.'); }
    if (!response.ok) throw new Error(payload.message || 'Protected setup request failed with HTTP ' + response.status + '.');
    return payload;
  };
  const gapCount = (module) => Array.isArray(module && module.upgrade_gaps) ? module.upgrade_gaps.length : 0;
  const present = (snapshot) => Number(snapshot && snapshot.total && snapshot.total.present || 0);
  const ready = (snapshot) => snapshot && snapshot.ready === true && snapshot.state === 'LIVE';

  button.addEventListener('click', async function () {
    if (button.disabled) return;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    const original = button.textContent;
    let previous = null;
    try {
      let snapshot = await request('status');
      if (ready(snapshot)) {
        setStatus('All source-declared module tables and structural checks are already verified.');
        window.location.reload();
        return;
      }
      previous = {
        tables: present(snapshot),
        finance: gapCount(snapshot.finance),
        hr: gapCount(snapshot.hr)
      };
      const max = Math.max(1, Math.min(160, Number(cfg.maxBatches || 160)));
      for (let index = 0; index < max && !ready(snapshot); index += 1) {
        button.textContent = 'Verifying setup… ' + (index + 1) + '/' + max;
        setStatus('Current stage: ' + String(snapshot.state || 'SETUP') + '. The server verifies every completed step.');
        let requestBody = {};
        if (snapshot.state === 'HR' && snapshot.hr && snapshot.hr.schema_approval_required) {
          const reason = window.prompt(
            'Owner approval required for additive HR/Payroll schema version ' + String(snapshot.hr.schema_version || '') + '. Confirm you reviewed the source DDL and have a restorable database/files backup. Enter the approval reason (at least 12 characters):',
            'Owner-approved additive HR/Payroll schema migration after backup and source review'
          );
          if (!reason || reason.trim().length < 12) throw new Error('HR/Payroll setup paused. No HR schema change was attempted because Owner approval was not provided.');
          requestBody = { approve_hr_schema: true, approval_reason: reason.trim() };
        }
        await request('advance', { method: 'POST', body: JSON.stringify(requestBody) });
        const next = await request('status');
        if (ready(next)) {
          setStatus('Core, Accounting/Procurement and HR/Payroll setup are physically verified. Refreshing System Control…');
          window.location.reload();
          return;
        }
        const current = {
          tables: present(next),
          finance: gapCount(next.finance),
          hr: gapCount(next.hr)
        };
        const progressed = current.tables > previous.tables || current.finance < previous.finance || current.hr < previous.hr;
        if (!progressed) {
          const detail = next.error && next.error.message ? ' ' + next.error.message : '';
          throw new Error('No physically verified progress was detected. Stop and review the server error and remaining table list.' + detail);
        }
        previous = current;
        snapshot = next;
      }
      if (!ready(snapshot)) throw new Error('The protected setup reached the per-run safety limit. Completed steps are retained; refresh and continue later.');
    } catch (error) {
      setStatus(error && error.message ? error.message : 'Protected setup stopped. Review the database error before retrying.', true);
    } finally {
      button.disabled = false;
      button.removeAttribute('aria-busy');
      button.textContent = original;
    }
  });
}());
