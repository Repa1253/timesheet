(function () {
  "use strict";

  const TS = window.Timesheet;
  if (!TS) return;

  const S = TS.state;
  const U = TS.util;

  TS.hr = TS.hr || {};
  const HR = TS.hr;

  // Fetch the last entry date for a user
  async function fetchLastEntryDate(userId) {
    const today = new Date();
    const toStr = today.toISOString().slice(0, 10);

    const from = new Date(today);
    from.setMonth(from.getMonth() - 6);
    const fromStr = from.toISOString().slice(0, 10);

    try {
      const entries = await TS.api(`/api/entries?user=${encodeURIComponent(userId)}&from=${fromStr}&to=${toStr}`);
      if (!Array.isArray(entries) || entries.length === 0) return null;

      let latest = null;
      for (const e of entries) {
        const d = e?.workDate;
        if (!d) continue;
        if (d > toStr) continue;
        if (!latest || d > latest) latest = d;  
      }
      return latest;
    } catch (error) {
      console.error(`❌ Failed to load the last entry for ${userId}:`, error);
      return null;
    }
  }

  // Update HR statistics summary
  function updateHrStats() {
    TS.dom.refresh();
    const dom = TS.dom;

    const userListEl = dom.userListEl || document.getElementById('hr-userlist');
    if (!userListEl) return;

    const elTotal = dom.hrStatsTotalEl || document.getElementById('hr-stat-total-hours');
    const elOver  = dom.hrStatsOvertimeEl || document.getElementById('hr-stat-total-overtime');
    const elNOver = dom.hrStatsNOvertimeEl || document.getElementById('hr-stat-employees-overtime');
    const elNeg   = dom.hrStatsMinusOvertimeEl || document.getElementById('hr-stat-total-negative');
    const elNNeg  = dom.hrStatsNMinusOvertimeEl || document.getElementById('hr-stat-employees-negative');
    const elSum   = dom.hrStatsSumOvertimeEl || document.getElementById('hr-stat-sum-overtimes');

    let totalMinutes = 0;
    let totalOvertimeMinutes = 0;
    let totalNOvertime = 0;
    let totalMinusOvertimeMinutes = 0;
    let totalNMinusOvertime = 0;

    userListEl.querySelectorAll('tr').forEach(tr => {
      const monthMinutes = parseInt(tr.dataset.totalMinutesMonth || '0', 10)
      if (!Number.isNaN(monthMinutes)) totalMinutes += monthMinutes;
      
      const balanceText = (tr.querySelector('.hr-user-balance')?.textContent || '').trim();
      const bal = U.hmToMin(balanceText);
      const minutes = (typeof bal === 'number' && Number.isFinite(bal)) ? bal : 0;

      if (minutes > 0) { totalOvertimeMinutes += minutes; totalNOvertime++; }
      if (minutes < 0) { totalMinusOvertimeMinutes += minutes; totalNMinusOvertime++; }
    });

    if (elTotal) elTotal.textContent = U.minToHm(totalMinutes);
    if (elOver)  elOver.textContent  = U.minToHm(totalOvertimeMinutes);
    if (elNOver) elNOver.textContent = String(totalNOvertime);
    if (elNeg)   elNeg.textContent   = U.minToHm(totalMinusOvertimeMinutes);
    if (elNNeg)  elNNeg.textContent  = String(totalNMinusOvertime);
    if (elSum)   elSum.textContent   = U.minToHm(totalOvertimeMinutes + totalMinusOvertimeMinutes);
  }

  // Load HR user list and their stats
  async function loadHrUserList() {
    TS.dom.refresh();
    const dom = TS.dom;
    if (!dom.userListEl) return;

    dom.userListEl.innerHTML = '';

    try {
      const users = await TS.api('/api/hr/users');
      const frag = document.createDocumentFragment();
      
      (users || []).forEach(({ id, name }) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><button type="button" data-user="${id}" class="hr-load-user">${name}</button></td>
          <td class="hr-user-target">--:--</td>
          <td class="hr-user-balance">--:--</td>
          <td class="hr-user-last-entry">-</td>
          <td class="hr-user-days-since"></td>
          <td class="hr-user-errors"></td>
        `;
        frag.appendChild(tr);

        // Load individual user HR data
        (async () => {
          try {
            const [cfg, overtime, lastDateStr] = await Promise.all([
              TS.api(`/api/hr/config/${encodeURIComponent(id)}`),
              TS.api(`/api/overtime/summary?user=${encodeURIComponent(id)}`),
              fetchLastEntryDate(id)
            ]);

            const dailyMinutes = U.pickDailyMin(cfg) ?? 480;
            const targetCell = tr.querySelector('.hr-user-target');
            if (targetCell) targetCell.textContent = U.minToHm(dailyMinutes);

            let overtimeMinutes = 0;
            if (overtime) {
              const totalMinutesMonth = overtime.totalMinutes ?? 0;
              tr.dataset.totalMinutesMonth = String(totalMinutesMonth);

              overtimeMinutes = overtime.overtimeMinutes ?? 0;
              const balanceCell = tr.querySelector('.hr-user-balance');
              if (balanceCell) balanceCell.textContent = U.minToHm(overtimeMinutes);
            }

            const lastCell = tr.querySelector('.hr-user-last-entry');
            if (lastCell && lastDateStr) {
              lastCell.textContent = U.formatDate(new Date(lastDateStr));
            }

            const daysCell  = tr.querySelector('.hr-user-days-since');
            const errorCell = tr.querySelector('.hr-user-errors');

            const errors = [];
            let diffDays = null;

            if (lastDateStr) {
              const today = new Date();
              const todayStr = today.toISOString().slice(0, 10);
              diffDays = Math.floor((Date.parse(todayStr) - Date.parse(lastDateStr)) / (1000 * 60 * 60 * 24));
              if (diffDays < 0) diffDays = 0;
              if (daysCell) daysCell.textContent = String(diffDays);
              if (diffDays >= 14) errors.push(t(S.appName, 'No entry for more than 14 days'));
            } else {
              errors.push(t(S.appName, 'No entry for more than 14 days'));
            }

            if (typeof overtimeMinutes === 'number') {
              if (overtimeMinutes > 600) errors.push(t(S.appName, 'Too much overtime'));
              else if (overtimeMinutes < -600) errors.push(t(S.appName, 'Too many negative hours'));
            }

            if (errorCell) errorCell.textContent = errors.join(', ');
            updateHrStats();
          } catch (error) {
            console.warn(`⚠️ Failed to load HR data for user ${id}:`, error);
          }
        })();
      });

      dom.userListEl.appendChild(frag);
    } catch (error) {
      console.warn('⚠️ Failed to load HR user list:', error);
    }
  }

  // Initialize HR module
  function init() {
    // Load HR user entries on button click
    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.hr-load-user');
      if (!btn) return;
      const userId = btn.dataset.user;
      if (!userId) return;

      const tabHrSection = document.getElementById('tab-hr');
      
      const hrConfigRow     = tabHrSection?.querySelector('.hr-config-row');
      const hrDailyMinInput = hrConfigRow?.querySelector('.config-daily-min');
      const hrStateInput    = hrConfigRow?.querySelector('.config-state');

      if (tabHrSection) {
        tabHrSection.querySelectorAll('.ts-hr-section').forEach(sec => sec.style.display = 'none');
      }

      S.hrCurrentMonth = new Date();
      TS.main?.updateHrMonthDisplay?.();

      if (hrDailyMinInput) hrDailyMinInput.value = '08:00';
      if (hrStateInput) hrStateInput.value = '';

      try {
        const cfg = await TS.api(`/api/hr/config/${encodeURIComponent(userId)}`);
        const min = U.pickDailyMin(cfg) ?? 480;
        const state = (typeof cfg?.state === 'string') ? cfg.state : '';

        if (hrDailyMinInput) hrDailyMinInput.value = U.minToHm(min);
        if (hrStateInput) hrStateInput.value = state;
      } catch {
        if (hrDailyMinInput) hrDailyMinInput.value = '08:00';
        if (hrStateInput) hrStateInput.value = '';
      }

      await TS.entries.loadUserEntries(userId, S.hrCurrentMonth);
      await TS.entries.refreshOvertimeTotal(userId, document.getElementById('tab-hr'));

      TS.dom.refresh();
      if (TS.dom.hrUserEntries) TS.dom.hrUserEntries.style.display = 'block';
      if (TS.dom.hrUserTitle) {
        const span = TS.dom.hrUserTitle.querySelector('span');
        if (span) span.textContent = userId;
      }
    });

    // Handle HR back button
    document.getElementById('hr-back-button')?.addEventListener('click', () => {
      const tabHrSection = document.getElementById('tab-hr');
      if (!tabHrSection) return;

      tabHrSection.querySelectorAll('.ts-hr-section').forEach(sec => {
        if (sec.id === 'hr-user-entries') sec.style.display = 'none';
        else sec.style.display = '';
      });
    });
  }

  // Expose public functions
  HR.init = init;
  HR.loadHrUserList = loadHrUserList;
  HR.updateHrStats = updateHrStats;
})();