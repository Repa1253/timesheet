(function () {
  'use strict';

  const TS = window.Timesheet;
  if (!TS) return;

  const S = TS.state;
  const U = TS.util;

  TS.entries = TS.entries || {};
  const EN = TS.entries;

  // Cache for holidays data
  async function getHolidays(year, state) {
    if (!state) return {};
    const cacheKey = `${state}_${year}`;
    if (S.holidayCache.has(cacheKey)) return S.holidayCache.get(cacheKey);
    
    try {
      const data = await TS.api(`/api/holidays?year=${year}&state=${encodeURIComponent(state)}`);
      if (data && typeof data === 'object') {
        S.holidayCache.set(cacheKey, data);
        return data;
      }
    } catch (e) {
      console.warn(`⚠️ Failed to load holidays for ${year} ${state}:`, e);
    }

    const empty = {};
    S.holidayCache.set(cacheKey, empty);
    return empty;
  }

  // Get the effective daily minimum minutes based on user config and input
  function getEffectiveDailyMin(contextRoot) {
    const cfgMin = U.pickDailyMin(S.userConfig);

    let inputMin = null;
    if (contextRoot) {
      const input = contextRoot.querySelector('.config-daily-min');
      if (input) inputMin = U.hmToMin(input.value || '');
    } else {
      const input = document.getElementById('config-daily-min-mine');
      if (input) inputMin = U.hmToMin(input.value || '');
    }
    return inputMin ?? cfgMin ?? 480;
  }

  // Create a table row element for the given date and entry data
  function createEntryRow(dateObj, entry, holidayMap = {}, dailyMin = null) {
    const dateStr = U.toLocalIsoDate(dateObj);
    const dayIndex = dateObj.getDay();
    const isHoliday = Object.prototype.hasOwnProperty.call(holidayMap, dateStr);
    const isWeekend = (dayIndex === 0 || dayIndex === 6);
    const statusText = isHoliday ? t(S.appName, 'Holiday') : (isWeekend ? t(S.appName, 'Weekend') : '');

    const startMin = entry?.startMin ?? null;
    const endMin   = entry?.endMin ?? null;
    const brkMin   = entry?.breakMinutes ?? 0;

    const durMin = (startMin != null && endMin != null) ? Math.max(0, endMin - startMin - brkMin) : null;
    const diffMin = (durMin != null && dailyMin != null) ? (durMin - dailyMin) : null;

    const warning = U.checkRules({ startMin, endMin, breakMinutes: brkMin }, dateStr, holidayMap);

    const startStr = startMin != null ? U.minToHm(startMin) : '';
    const endStr   = endMin   != null ? U.minToHm(endMin)   : '';
    const breakStr = String(brkMin ?? 0);
    const commentStr = entry?.comment ?? '';
    const durStr  = U.minToHm(durMin);
    const diffStr = U.minToHm(diffMin);

    const tr = document.createElement('tr');
    tr.dataset.date = dateStr;
    if (entry?.id) tr.dataset.id = entry.id;

    tr.dataset.savedStart = startStr;
    tr.dataset.savedEnd = endStr;
    tr.dataset.savedBreak = breakStr;
    tr.dataset.savedComment = commentStr;

    if (isHoliday || isWeekend) tr.classList.add('is-weekend-row');
    
    const today = new Date();
    if (
      dateObj.getDate() === today.getDate() &&
      dateObj.getMonth() === today.getMonth() &&
      dateObj.getFullYear() === today.getFullYear()
    ) {
      tr.classList.add('ts-today');
    }

    tr.innerHTML = `
      <td>${U.formatDate(dateObj)}</td>
      <td>${U.dayLabel(dayIndex)}</td>
      <td class="ts-status ${isWeekend ? 'is-weekend' : ''}">${statusText}</td>
      <td><input type="time" class="startTime" value="${startStr}"></td>
      <td><input type="text" class="breakMinutes" inputmode="text" pattern="^-?\d+(?::\d+)?$" value="${breakStr}"></td>
      <td><input type="time" class="endTime" value="${endStr}"></td>
      <td class="ts-duration">${durStr}</td>
      <td class="ts-diff">${diffStr}</td>
      <td><textarea class="commentInput">${commentStr}</textarea></td>
      <td class="ts-warn">${warning}</td>
    `;
    return tr;
  }

  // Update the total worked hours and overtime for the month in the given table body
  function updateWorkedHours(anyRow) {
    const tbody = anyRow.closest('tbody');
    if (!tbody) return;

    let totalMinutes = 0;
    let workedDays   = 0;

    tbody.querySelectorAll('tr').forEach(row => {
      const durText = row.querySelector('.ts-duration')?.textContent.trim();
      const durMin  = U.hmToMin(durText);
      if (durMin != null) {
        totalMinutes += durMin;
        workedDays++;
      }
    });

    const container = anyRow.closest('#hr-user-entries');
    const root = container || document;

    const workedEl   = root.querySelector('#worked-hours-month');
    const overtimeEl = root.querySelector('#overtime-month');

    const dailyMin = getEffectiveDailyMin(container);
    const overtime = totalMinutes - (workedDays * dailyMin);

    if (workedEl)   workedEl.textContent   = U.minToHm(totalMinutes);
    if (overtimeEl) overtimeEl.textContent = U.minToHm(overtime);
  }

  // Refresh the total overtime hours for the given user
  async function refreshOvertimeTotal(userId = null, container = document) {
    try {
      const uid = userId || S.currentUserId;
      if (!uid) return;

      const overtimeTotalEl = container.querySelector('#overtime-total');
      if (!overtimeTotalEl) return;

      const data = await TS.api(`/api/overtime/summary?user=${encodeURIComponent(uid)}`);
      overtimeTotalEl.textContent = U.minToHm(data?.overtimeMinutes ?? 0);
    } catch (error) {
      console.error('❌ Failed to load total overtime hours:', error);
    }
  }

  // Load entries for the given user and month
  async function loadUserEntries(userId = null, date = new Date()) {
    const { from, to } = U.getMonthRange(date);
    const fromStr = from.toISOString().slice(0, 10);
    const toStr   = to.toISOString().slice(0, 10);

    const query   = userId
      ? `/api/entries?user=${encodeURIComponent(userId)}&from=${fromStr}&to=${toStr}`
      : `/api/entries?from=${fromStr}&to=${toStr}`;

    const entries = await TS.api(query).catch(error => {
      console.error('❌ Failed to load entries:', error);
      return [];
    });

    TS.dom.refresh();
    const body = userId ? TS.dom.hrUserBody : TS.dom.tsBody;
    if (!body) return;
    body.innerHTML = '';

    const entryMap = {};
    (entries || []).forEach(e => { if (e?.workDate) entryMap[e.workDate] = e; });

    const year = from.getFullYear();

    let stateCode;
    if (userId) {
      const hrStateInput = document.querySelector('#tab-hr .config-state');
      stateCode = (hrStateInput?.value);
    } else {
      const mineStateInput = document.querySelector('#tab-mine .config-state');
      stateCode = (mineStateInput?.value || S.userConfig?.state);
    }

    const holidayMap = await getHolidays(year, stateCode);

    const container = userId ? document.getElementById('hr-user-entries') : null;
    const dailyMin = getEffectiveDailyMin(container);

    const frag = document.createDocumentFragment();
    for (let d = new Date(from); d <= to; d.setDate(d.getDate() + 1)) {
      const dateKey = U.toLocalIsoDate(d);
      const entry = entryMap[dateKey];
      const row = createEntryRow(new Date(d), entry, holidayMap, dailyMin);
      frag.appendChild(row);
    }
    body.appendChild(frag);

    const firstRow = body.querySelector('tr');
    if (firstRow) updateWorkedHours(firstRow);
  }

  async function deleteEntryForRow(row) {
    const entryId = row.dataset.id || null;
    if (!entryId) return;

    const startInput   = row.querySelector('.startTime');
    const endInput     = row.querySelector('.endTime');
    const breakInput   = row.querySelector('.breakMinutes');
    const commentInput = row.querySelector('.commentInput');
    const warnCell     = row.querySelector('.ts-warn');
    const durCell      = row.querySelector('.ts-duration');
    const diffCell     = row.querySelector('.ts-diff');

    await TS.api(`/api/entries/${encodeURIComponent(entryId)}`, { method: 'DELETE' });

    delete row.dataset.id;

    if (startInput)   startInput.value   = '';
    if (endInput)     endInput.value     = '';
    if (breakInput)   breakInput.value   = '0';
    if (commentInput) commentInput.value = '';

    row.dataset.savedStart   = '';
    row.dataset.savedEnd     = '';
    row.dataset.savedBreak   = '0';
    row.dataset.savedComment = '';

    if (warnCell) warnCell.textContent = '';
    if (durCell)  durCell.textContent  = '--:--';
    if (diffCell) diffCell.textContent = '--:--';

    updateWorkedHours(row);

    const isHr = !!row.closest('#hr-user-entries');
    const uid = isHr ? document.querySelector('#hr-user-title span')?.textContent : S.currentUserId;
    if (uid) await refreshOvertimeTotal(uid, isHr ? document.getElementById('tab-hr') : document);

    U.showRowSavedFeedback(row);
  }

  // Save the row if there are changes
  async function saveRowIfNeeded(row) {
    if (row.dataset.saving === '1') return;

    const isHr = !!row.closest('#hr-user-entries');

    const startInput   = row.querySelector('.startTime');
    const endInput     = row.querySelector('.endTime');
    const breakInput   = row.querySelector('.breakMinutes');
    const commentInput = row.querySelector('.commentInput');

    const warnCell = row.querySelector('.ts-warn');
    const durCell  = row.querySelector('.ts-duration');
    const diffCell = row.querySelector('.ts-diff');

    if (!startInput || !endInput || !breakInput) return;

    const workDate = row.dataset.date;
    if (!workDate) return;

    const startVal = (startInput.value    || '').trim();
    const endVal   = (endInput.value      || '').trim();
    const comment  = (commentInput?.value || '').trim();

    const hasStart = !!startVal;
    const hasEnd   = !!endVal;
    const hasBothTimes = hasStart && hasEnd;

    const hasId = !!row.dataset.id;

    const savedStart   = row.dataset.savedStart || '';
    const savedEnd     = row.dataset.savedEnd   || '';
    const savedBreak   = row.dataset.savedBreak != null ? parseInt(row.dataset.savedBreak, 10) : 0;
    const savedComment = String(row.dataset.savedComment ?? '').trim();

    if (!(hasStart || hasEnd) && comment.length === 0) {
      if (hasId) {
        row.dataset.saving = '1';
        try { await deleteEntryForRow(row); } 
        catch (error) { console.error('❌ Auto-Delete failed:', error); } 
        finally { delete row.dataset.saving; }
      } else {
        if (warnCell) warnCell.textContent = '';
        if (durCell)  durCell.textContent  = '--:--';
        if (diffCell) diffCell.textContent = '--:--';
      }
      return;
    }

    // Full save: start+end present
    // Comment-only: comment present, but times NOT complete
    // Partial time without comment: block

    const isCommentOnly = comment.length > 0 && !hasBothTimes;

    // Block partial time without comment
    if (!hasBothTimes && !isCommentOnly) {
      if (warnCell) warnCell.textContent = t(S.appName, 'Time incomplete');
      return;
    }

    let breakMin = 0;

    // Full save
    if (!isCommentOnly) {
      breakMin = U.parseBreakMinutesInput(breakInput.value);
      if (breakMin == null) {
        breakInput.value = String(Number.isFinite(savedBreak) ? savedBreak : 0);
        return;
      }
      breakInput.value = String(breakMin);

      const startMin = U.hmToMin(startVal);
      const endMin   = U.hmToMin(endVal);
      const duration = (startMin != null && endMin != null) ? Math.max(0, endMin - startMin - breakMin) : null;

      let stateCode;
      if (isHr) {
        const hrStateInput = document.querySelector('#tab-hr .config-state');
        stateCode = (hrStateInput?.value);
      } else {
        const mineStateInput = document.querySelector('#tab-mine .config-state');
        stateCode = (mineStateInput?.value || S.userConfig?.state);
      }

      const holidayMap = S.holidayCache.get(`${stateCode}_${workDate.slice(0, 4)}`) || {};
      const baseDailyMin = getEffectiveDailyMin(isHr ? document.getElementById('hr-user-entries') : null);
      const diffMin = (duration != null && baseDailyMin != null) ? (duration - baseDailyMin) : null;

      if (warnCell) warnCell.textContent = U.checkRules({ startMin, endMin, breakMinutes: breakMin }, workDate, holidayMap);
      if (durCell)  durCell.textContent  = U.minToHm(duration);
      if (diffCell) diffCell.textContent = U.minToHm(diffMin);

      // Check if anything changed
      if (startVal === savedStart && endVal === savedEnd && breakMin === savedBreak && comment === savedComment) {
        return;
      }
    // Comment-only save
    } else {
      // Show warning for incomplete time
      if (warnCell) warnCell.textContent = (hasStart || hasEnd) ? t(S.appName, 'Time incomplete') : '';

      // Check if anything changed
      if (!(savedStart !== '' || savedEnd !== '' || (Number.isFinite(savedBreak) ? savedBreak : 0) !== 0) && comment === savedComment) {
        return;
      }
    }

    row.dataset.saving = '1';

    try {
      let savedEntry;
      const payload = isCommentOnly
        ? { workDate, comment, commentOnly: 1 }
        : { workDate, start: startVal, end: endVal, breakMinutes: breakMin, comment };

      if (hasId) {
        savedEntry = await TS.api(`/api/entries/${encodeURIComponent(row.dataset.id)}`, {
          method: 'PUT',
          body: JSON.stringify(payload)
        });
      } else {
        const targetUserId = isHr ? document.querySelector('#hr-user-title span')?.textContent : null;
        const createPath = (isHr && targetUserId) 
          ? `/api/entries?user=${encodeURIComponent(targetUserId)}` 
          : `/api/entries`;

        savedEntry = await TS.api(createPath, {
          method: 'POST',
          body: JSON.stringify(payload)
        });

        if (savedEntry?.id) row.dataset.id = savedEntry.id;
      }

      if (isCommentOnly) {
        if (startInput) startInput.value = '';
        if (endInput)   endInput.value   = '';
        if (breakInput) breakInput.value = '0';
        if (warnCell)   warnCell.textContent = '';
        if (durCell)    durCell.textContent  = '--:--';
        if (diffCell)   diffCell.textContent = '--:--';
      }

      if (!isCommentOnly) {
        row.dataset.savedStart   = startVal;
        row.dataset.savedEnd     = endVal;
        row.dataset.savedBreak   = String(breakMin);
        row.dataset.savedComment = comment;
      } else {
        row.dataset.savedStart   = '';
        row.dataset.savedEnd     = '';
        row.dataset.savedBreak   = '0';
        row.dataset.savedComment = comment;
      }

      updateWorkedHours(row);

      const uid = isHr ? document.querySelector('#hr-user-title span')?.textContent : S.currentUserId;
      if (uid) await refreshOvertimeTotal(uid, isHr ? document.getElementById('tab-hr') : document);

      U.showRowSavedFeedback(row);
    } catch (error) {
      console.error('❌ Auto-Save failed:', error);
    } finally {
      delete row.dataset.saving;
    }
  }

  // Export functions
  EN.getHolidays = getHolidays;
  EN.getEffectiveDailyMin = getEffectiveDailyMin;
  EN.createEntryRow = createEntryRow;
  EN.loadUserEntries = loadUserEntries;
  EN.updateWorkedHours = updateWorkedHours;
  EN.refreshOvertimeTotal = refreshOvertimeTotal;
  EN.deleteEntryForRow = deleteEntryForRow;
  EN.saveRowIfNeeded = saveRowIfNeeded;
})();