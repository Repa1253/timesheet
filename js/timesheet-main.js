(function () {
  "use strict";

  const TS = window.Timesheet;
  if (!TS) {
    console.error('Timesheet: core not loaded');
    return;
  }

  const S = TS.state;
  const U = TS.util;

  TS.main = TS.main || {};
  const MAIN = TS.main;

  // Update month display
  function updateMonthDisplay() {
    const el = document.getElementById('month-display');
    if (el) el.textContent = U.monthLabel(S.currentMonth);
  }

  // Update HR month display
  function updateHrMonthDisplay() {
    const el = document.getElementById('hr-month-display');
    if (el) el.textContent = U.monthLabel(S.hrCurrentMonth);
  }

  // Expose functions
  MAIN.updateMonthDisplay = updateMonthDisplay;
  MAIN.updateHrMonthDisplay = updateHrMonthDisplay;

  // Bind month navigation buttons
  function bindMonthNavigation() {
    // Previous month button
    TS.$('#month-prev')?.addEventListener('click', () => {
      S.currentMonth.setMonth(S.currentMonth.getMonth() - 1);
      updateMonthDisplay();
      TS.entries.loadUserEntries(null, S.currentMonth);
    });

    // Next month button
    TS.$('#month-next')?.addEventListener('click', () => {
      S.currentMonth.setMonth(S.currentMonth.getMonth() + 1);
      updateMonthDisplay();
      TS.entries.loadUserEntries(null, S.currentMonth);
    });

    // Previous month button for HR view
    TS.$('#hr-month-prev')?.addEventListener('click', () => {
      S.hrCurrentMonth.setMonth(S.hrCurrentMonth.getMonth() - 1);
      updateHrMonthDisplay();
      const uid = TS.dom.hrUserTitle?.querySelector('span')?.textContent;
      if (uid) TS.entries.loadUserEntries(uid, S.hrCurrentMonth);
    });

    // Next month button for HR view
    TS.$('#hr-month-next')?.addEventListener('click', () => {
      S.hrCurrentMonth.setMonth(S.hrCurrentMonth.getMonth() + 1);
      updateHrMonthDisplay();
      const uid = TS.dom.hrUserTitle?.querySelector('span')?.textContent;
      if (uid) TS.entries.loadUserEntries(uid, S.hrCurrentMonth);
    });

    // Month display click to go to current month
    TS.$('#month-display')?.addEventListener('click', () => {
      const today = new Date();
      if (S.currentMonth.getFullYear() === today.getFullYear() && S.currentMonth.getMonth() === today.getMonth()) return;
      S.currentMonth = today;
      updateMonthDisplay();
      TS.entries.loadUserEntries(null, S.currentMonth);
    });

    // HR month display click to go to current month
    TS.$('#hr-month-display')?.addEventListener('click', () => {
      const today = new Date();
      if (S.hrCurrentMonth.getFullYear() === today.getFullYear() && S.hrCurrentMonth.getMonth() === today.getMonth()) return;
      S.hrCurrentMonth = today;
      updateHrMonthDisplay();
      const uid = TS.dom.hrUserTitle?.querySelector('span')?.textContent;
      if (uid) TS.entries.loadUserEntries(uid, S.hrCurrentMonth);
    });
  }

  // Bind tab switching
  function bindTabSwitching() {
    TS.$$('.ts-tab').forEach(tabButton => {
      tabButton.addEventListener('click', () => {
        TS.$$('.ts-tab').forEach(btn => btn.classList.remove('active'));
        TS.$$('.ts-tabview').forEach(view => view.classList.remove('active'));

        tabButton.classList.add('active');
        const targetView = document.getElementById(`tab-${tabButton.dataset.tab}`);
        if (targetView) targetView.classList.add('active');

        if (tabButton.dataset.tab === 'hr') {
          TS.hr.loadHrUserList();
        }
      });
    });
  }

  // Bind auto-save on input blur and Enter key
  function bindAutoSave() {
    // Handle blur event for saving
    document.addEventListener('blur', async (e) => {
      const el = e.target;
      if (!(el instanceof HTMLElement)) return;

      if (
        !el.classList.contains('startTime') &&
        !el.classList.contains('endTime') &&
        !el.classList.contains('breakMinutes') &&
        !el.classList.contains('commentInput')
      ) return;

      const row = el.closest('tr');
      if (!row) return;

      await TS.entries.saveRowIfNeeded(row);
    }, true);

    // Handle Enter key for saving
    document.addEventListener('keydown', async (e) => {
      if (e.key !== 'Enter' || e.shiftKey) return;

      const el = e.target;
      if (
        !el.classList.contains('startTime') &&
        !el.classList.contains('endTime') &&
        !el.classList.contains('breakMinutes') &&
        !el.classList.contains('commentInput')
      ) return;

      e.preventDefault();
      const row = el.closest('tr');
      if (!row) return;

      await TS.entries.saveRowIfNeeded(row);
    });
  }

  // Bind export buttons
  function bindExportButtons() {
    document.getElementById('export-mine-xlsx')?.addEventListener('click', () => TS.export.handleExportClick('mine'));;
    document.getElementById('export-hr-xlsx')?.addEventListener('click', () => TS.export.handleExportClick('hr'));
  }

  // Initialize main module
  async function init() {
    TS.dom.refresh();

    TS.copy.init();
    TS.hr.init();
    TS.config.init();

    bindMonthNavigation();
    bindTabSwitching();
    bindAutoSave();
    bindExportButtons();

    updateMonthDisplay();
    updateHrMonthDisplay();

    const cfgReady = S.currentUserId ? TS.config.loadUserConfig(S.currentUserId) : Promise.resolve();

    await TS.entries.loadUserEntries(null, S.currentMonth);
    await cfgReady;
    await TS.entries.refreshOvertimeTotal(S.currentUserId, document);

    const initRow = document.querySelector('#ts-body tr');
    if (initRow) TS.entries.updateWorkedHours(initRow);
  }

  // Start initialization on DOM ready
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();