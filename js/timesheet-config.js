(function () {
  "use strict";

  const TS = window.Timesheet;
  if (!TS) return;

  const S = TS.state;
  const U = TS.util;

  TS.config = TS.config || {};
  const CFG = TS.config;

  // Load user configuration
  async function loadUserConfig(uid) {
    try {
      const cfg = await TS.api(`/api/hr/config/${encodeURIComponent(uid)}`);
      const minutes = U.pickDailyMin(cfg);
      S.userConfig = { ...(cfg || {}), dailyMin: minutes, workMinutes: minutes };

      if (minutes != null) {
        U.setConfigInputs(minutes, cfg?.state ?? null);
      } else if (cfg?.state != null) {
        U.setConfigInputs(null, cfg.state);
      }
    } catch (error) {
      console.warn('⚠️ Could not load user configuration', error);
    }
  }

  async function loadHrNotificationSettings() {
    if (!S.currentUserId) return;

    TS.dom.refresh();
    const dom = TS.dom;
    if (!dom.hrNotificationsSection) return;

    try {
      const settings = await TS.api('/api/hr/notifications');
      S.hrNotificationSettings = settings || null;

      if (!dom.hrMailNoEntryEnabled) return;

      dom.hrMailNoEntryEnabled.checked = !!settings?.noEntryEnabled;
      dom.hrMailNoEntryDays.value      = String(settings?.noEntryDays ?? 14);

      dom.hrMailOvertimeEnabled.checked = !!settings?.overtimeEnabled;
      dom.hrMailOvertimeThreshold.value = U.minToHm(settings?.overtimeThresholdMinutes ?? 600).replace('-', '');
      dom.hrMailNegativeEnabled.checked = !!settings?.negativeOvertimeEnabled;
      dom.hrMailNegativeThreshold.value = U.minToHm(settings?.negativeOvertimeThresholdMinutes ?? 600).replace('-', '');
    } catch (error) {
      console.warn('⚠️ Could not load HR notification settings', error);
    }
  }

  async function saveHrNotificationSettings() {
    if (!S.currentUserId) return;

    TS.dom.refresh();
    const dom = TS.dom;
    if (!dom.hrNotificationsSection) return;

    const days = parseInt(dom.hrMailNoEntryDays?.value || '14', 10);
    const otMin  = U.hmToMin(dom.hrMailOvertimeThreshold?.value || '10:00');
    const negMin = U.hmToMin(dom.hrMailNegativeThreshold?.value || '10:00');

    const payload = {
      noEntryEnabled: !!dom.hrMailNoEntryEnabled?.checked,
      noEntryDays: Number.isFinite(days) ? Math.min(365, Math.max(1, days)) : 14,

      overtimeEnabled: !!dom.hrMailOvertimeEnabled?.checked,
      overtimeThresholdMinutes: (typeof otMin === 'number' && Number.isFinite(otMin)) ? Math.max(0, otMin) : 600,

      negativeOvertimeEnabled: !!dom.hrMailNegativeEnabled?.checked,
      negativeOvertimeThresholdMinutes: (typeof negMin === 'number' && Number.isFinite(negMin)) ? Math.max(0, negMin) : 600,
    };

    try {
      const result = await TS.api('/api/hr/notifications', {
        method: 'PUT',
        body: JSON.stringify(payload),
      });

      S.hrNotificationSettings = result || payload;
      TS.notify(t(S.appName, 'Saved'));
    } catch (error) {
      console.error('❌ Failed to save HR notification settings:', error);
      TS.notify(t(S.appName, 'Save failed'));
    }
  }

  async function loadHrWarningThresholds() {
    if (!S.currentUserId) return;

    TS.dom.refresh();
    const dom = TS.dom;
    if (!dom.hrWarningThresholdsSection) return;

    try {
      const settings = await TS.api('/api/hr/warnings');
      S.hrWarningThresholds = settings || null;

      if (!dom.hrWarnNoEntryDays) return;

      dom.hrWarnNoEntryDays.value = String(settings?.noEntryDays ?? 14);
      dom.hrWarnOvertimeThreshold.value = U.minToHm(settings?.overtimeThresholdMinutes ?? 600).replace('-', '');
      dom.hrWarnNegativeThreshold.value = U.minToHm(settings?.negativeOvertimeThresholdMinutes ?? 600).replace('-', '');
    } catch (error) {
      console.warn('âš ï¸ Could not load HR warning thresholds', error);
    }
  }

  async function saveHrWarningThresholds() {
    if (!S.currentUserId) return;

    TS.dom.refresh();
    const dom = TS.dom;
    if (!dom.hrWarningThresholdsSection) return;

    const days = parseInt(dom.hrWarnNoEntryDays?.value || '14', 10);
    const otMin  = U.hmToMin(dom.hrWarnOvertimeThreshold?.value || '10:00');
    const negMin = U.hmToMin(dom.hrWarnNegativeThreshold?.value || '10:00');

    const payload = {
      noEntryDays: Number.isFinite(days) ? Math.min(365, Math.max(1, days)) : 14,
      overtimeThresholdMinutes: (typeof otMin === 'number' && Number.isFinite(otMin)) ? Math.max(0, otMin) : 600,
      negativeOvertimeThresholdMinutes: (typeof negMin === 'number' && Number.isFinite(negMin)) ? Math.max(0, negMin) : 600,
    };

    try {
      const result = await TS.api('/api/hr/warnings', {
        method: 'PUT',
        body: JSON.stringify(payload),
      });

      S.hrWarningThresholds = result || payload;
      TS.notify(t(S.appName, 'Saved'));

      const hrTabActive = document.getElementById('tab-hr')?.classList.contains('active');
      if (hrTabActive && TS.hr?.loadHrUserList) {
        TS.hr.loadHrUserList();
      }
    } catch (error) {
      console.error('âŒ Failed to save HR warning thresholds:', error);
      TS.notify(t(S.appName, 'Save failed'));
    }
  }

  // Initialize configuration handlers
  function init() {
    TS.dom.refresh();
    const dom = TS.dom;

    // Save configuration button handlers
    (dom.saveConfigBtns || []).forEach(btn => {
      btn.addEventListener('click', async () => {
        if (!S.currentUserId) return;
      
        const container = btn.closest('.hr-config-row') || document;
        const dailyInput = container.querySelector('.config-daily-min') || dom.dailyMinInputs?.[0];
        const stateInput = container.querySelector('.config-state')     || dom.stateInputs?.[0];

        const timeStr = dailyInput?.value || '';
        const state   = stateInput?.value || '';
        const minutes = U.hmToMin(timeStr) ?? 480;
        
        const inHr =   !!btn.closest('#tab-hr');

        let targetUserId = S.currentUserId;
        if (inHr) {
          const selectedId = TS.dom.hrUserTitle?.querySelector('span')?.textContent?.trim();
          if (selectedId) targetUserId = selectedId;
        }

        btn.disabled = true;

        try {
          await TS.api(`/api/hr/config/${encodeURIComponent(targetUserId)}`, {
            method: 'PUT',
            body: JSON.stringify({ dailyMin: minutes, state })
          });
      
          if (targetUserId === S.currentUserId) {
            S.userConfig = { ...(S.userConfig || {}), dailyMin: minutes, workMinutes: minutes, state };
            U.setConfigInputs(minutes, state);
            
            const firstRow = TS.dom.tsBody?.querySelector('tr');
            if (firstRow) TS.entries.updateWorkedHours(firstRow);
            await TS.entries.refreshOvertimeTotal(S.currentUserId, document);
          } else {
            const firstHrRow = TS.dom.hrUserBody?.querySelector('tr');
            if (firstHrRow) TS.entries.updateWorkedHours(firstHrRow);
            await TS.entries.refreshOvertimeTotal(targetUserId, document.getElementById('tab-hr'));
          }
        } catch (error) {
          console.error('❌ Failed to save configuration:', error);
        } finally {
          btn.disabled = false;
        }
      });
    });

    // Update worked hours when daily minimum changes
    (dom.dailyMinInputs || []).forEach(input => {
      input.addEventListener('input', () => {
        const inMine = !!input.closest('#tab-mine');
        const inHr =   !!input.closest('#tab-hr');

        if (inMine && TS.dom.tsBody) {
          const firstRow = TS.dom.tsBody.querySelector('tr');
          if (firstRow) TS.entries.updateWorkedHours(firstRow);
        }

        if (inHr && TS.dom.hrUserBody) {
          const firstHrRow = TS.dom.hrUserBody.querySelector('tr');
          if (firstHrRow) TS.entries.updateWorkedHours(firstHrRow);
        }
      });
    });

    // HR Notification Settings
    if (dom.hrNotificationsSection) {
      let booting = true;
      let timer = null;

      const scheduleSave = () => {
        if (booting) return;
        clearTimeout(timer);
        timer = setTimeout(() => saveHrNotificationSettings(), 450);
      };

      const inputs = [
        dom.hrMailNoEntryEnabled,
        dom.hrMailNoEntryDays,
        dom.hrMailOvertimeEnabled,
        dom.hrMailOvertimeThreshold,
        dom.hrMailNegativeEnabled,
        dom.hrMailNegativeThreshold,
      ].filter(Boolean);

      inputs.forEach(el => {
        el.addEventListener('change', scheduleSave);
        el.addEventListener('input', scheduleSave); 
      });

      (async () => {
        await loadHrNotificationSettings();
        booting = false;
      })();
    }

    // HR Warning Thresholds
    if (dom.hrWarningThresholdsSection) {
      let booting = true;
      let timer = null;

      const scheduleSave = () => {
        if (booting) return;
        clearTimeout(timer);
        timer = setTimeout(() => saveHrWarningThresholds(), 450);
      };

      const inputs = [
        dom.hrWarnNoEntryDays,
        dom.hrWarnOvertimeThreshold,
        dom.hrWarnNegativeThreshold,
      ].filter(Boolean);

      inputs.forEach(el => {
        el.addEventListener('change', scheduleSave);
        el.addEventListener('input', scheduleSave);
      });

      (async () => {
        await loadHrWarningThresholds();
        booting = false;
      })();
    }
  }

  // Expose functions
  CFG.init = init;
  CFG.loadUserConfig = loadUserConfig;
  CFG.loadHrWarningThresholds = loadHrWarningThresholds;
})();
