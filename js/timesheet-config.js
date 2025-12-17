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
  }

  // Expose functions
  CFG.init = init;
  CFG.loadUserConfig = loadUserConfig;
})();