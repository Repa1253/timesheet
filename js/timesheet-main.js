(async function () {
  "use strict";

  /** 
  * State & Configuration 
  */

  const token = OC.requestToken; // Nextcloud CSRF token
  const currentUserId = (function getCurrentUserId() {
    // Retrieve the logged-in user’s UID via Nextcloud global (if available)
    try {
      if (OC && typeof OC.getCurrentUser === 'function') {
        return OC.getCurrentUser().uid;
      } else if (OC && OC.currentUser) {
        return OC.currentUser;  // Fallback for older NC versions
      }
    } catch (error) {
      console.warn('⚠️ Konnte currentUserId nicht ermitteln:', error);
    }
    return null;
  })();

  // Global state for current user’s config (daily minutes and state)
  let userConfig = null;

  // Month trackers for personal and HR views
  let currentMonth = new Date();
  let hrCurrentMonth = new Date();

  // Feiertags-Cache (state+year → holidays)
  const holidayCache = new Map();

  // Cache frequently used DOM elements
  const tsBody        = document.getElementById('ts-body'); // tbody for current user's entries
  const hrUserBody    = document.getElementById('hr-user-body'); // tbody for HR-selected user's entries
  const hrUserEntries = document.getElementById('hr-user-entries'); // container section for HR user entries
  const hrUserTitle   = document.getElementById('hr-user-title'); // heading that displays selected user ID
  const userListEl    = document.getElementById('hr-userlist'); // list of users for HR view
  const dailyMinInputs = Array.from(document.querySelectorAll('.config-daily-min'));
  const stateInputs    = Array.from(document.querySelectorAll('.config-state'));
  const saveConfigBtns = Array.from(document.querySelectorAll('.save-config-btn'));

  // Short alias for querySelector (for convenience in this script)
  const $ = (sel) => document.querySelector(sel);

  /**
  * Utility Functions 
  */

  // Weekday abbreviations
  const days = [t('timesheet', 'Sun'), 
                t('timesheet', 'Mon'), 
                t('timesheet', 'Tue'), 
                t('timesheet', 'Wed'), 
                t('timesheet', 'Thu'), 
                t('timesheet', 'Fri'), 
                t('timesheet', 'Sat')
  ];

  function formatDate(dateObj) {
    // Format a Date to DD.MM.YYYY (German locale style)
    const day = dateObj.getDate().toString().padStart(2, '0');
    const month = (dateObj.getMonth() + 1).toString().padStart(2, '0');
    const year = dateObj.getFullYear();
    return `${day}.${month}.${year}`;
  }

  function getMonthRange(date) {
    // Given a Date, return the first and last date of that month
    const y = date.getFullYear();
    const m = date.getMonth();
    const from = new Date(y, m, 1);
    const to = new Date(y, m + 1, 0);
    return { from, to };
  }

  function minToHm(min) {
    // Convert minutes (number or null) to "HH:MM" string (or "--:--" if null/undefined)
    if (min == null) return '--:--';
    const sign = min < 0 ? '-' : '';
    const absMin = Math.abs(min);
    const h = String(Math.floor(absMin / 60)).padStart(2, '0');
    const m = String(absMin % 60).padStart(2, '0');
    return `${sign}${h}:${m}`;
  }

  function hmToMin(str) {
    // Convert "HH:MM" string to total minutes (number). Returns null for invalid input.
    if (!str) return null;
    const [h, m] = str.split(':').map(Number);
    if (Number.isNaN(h) || Number.isNaN(m)) return null;
    return h * 60 + m;
  }

  function pickDailyMin(cfg) {
    // Determine the daily minutes value from a config object (which may use different keys)
    if (!cfg) return null;
    if (Number.isFinite(cfg.dailyMin)) return cfg.dailyMin;
    if (Number.isFinite(cfg.workMinutes)) return cfg.workMinutes;
    if (typeof cfg.workMinutes === 'string') return hmToMin(cfg.workMinutes);
    return null;
  }

  function checkRules(entry, dateStr = null, holidayMap = {}) {
    // Validate an entry’s timing rules and return a comma-separated string of issues.
    const start = entry.startMin ?? hmToMin(entry.start);
    const end   = entry.endMin ?? hmToMin(entry.end);
    if (start == null || end == null) return '';

    const brk = entry.breakMinutes ?? 0;
    const gross = end - start;
    const dur   = Math.max(0, gross - brk);

    const issues = [];

    // Zeitliche Grenzen
    if (dur > 10 * 60) issues.push(t('timesheet', 'Above maximum time'));

    // Pausenregelung
    if (dur > 9 * 60 && brk < 45) {
      issues.push(t('timesheet', 'Break too short'));
    } else if (dur > 6 * 60 && brk < 30) {
      issues.push(t('timesheet', 'Break too short'));
    }

    // Kalenderregeln
    if (dateStr) {
      const date = new Date(dateStr);
      const isSunday = date.getDay() === 0;
      const isHoliday = holidayMap && holidayMap[dateStr];

      if (isSunday) issues.push(t('timesheet', 'Sunday work not allowed'));
      if (isHoliday) issues.push(t('timesheet', 'Holiday work not allowed'));
    }

    return issues.join(', ');
  }

  function toLocalIsoDate(dateObj) {
    const year  = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day   = String(dateObj.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  async function fetchLastEntryDate(userId) {
    const today = new Date();
    const toStr = today.toISOString().slice(0, 10);

    const from = new Date(today);
    from.setMonth(from.getMonth() - 6);
    const fromStr = from.toISOString().slice(0, 10);

    try {
      const entries = await api(`/api/entries?user=${encodeURIComponent(userId)}&from=${fromStr}&to=${toStr}`);

      if (!Array.isArray(entries) || entries.length === 0) {
        return null;
      }

      let latest = null;
      for (const e of entries) {
        const d = e.workDate;
        if (!d) continue;
        if (d > toStr) continue; // alles nach heute ignorieren
        if (!latest || d > latest) {
          latest = d;  
        }
      }

      return latest;
    } catch (error) {
      console.error(`❌ Laden des letzten Eintrags für ${userId} fehlgeschlagen:`, error);
      return null;
    }
  }

  function showRowSavedFeedback(row) {
    row.classList.add('ts-row-saved');
    setTimeout(() => {
      row.classList.remove('ts-row-saved');
    }, 1200);
  }

  function setConfigInputs(minutes, state) {
    const timeStr = minutes != null ? minToHm(minutes) : '';
    dailyMinInputs.forEach(input => { input.value = timeStr; });
    stateInputs.forEach(input => { input.value = state ?? ''; });
  }

  function toCsv(rows, sep = ';') {
    return rows.map(cols => cols.map(val => {
      const s = val == null ? '' : String(val);
      if (s.includes(sep) || s.includes('"') || s.includes('\n')) {
        return '"' + s.replace(/"/g, '""') + '"';
      }
      return s;
    }).join(sep)).join('\r\n');
  }

  function exportCurrentViewToCsv(isHr) {
    const container = isHr ? hrUserEntries : document.getElementById('tab-mine');
    if (!container) return;

    const table = isHr ? container.querySelector('#hr-user-table') : container.querySelector('#ts-table');
    if (!table) return;

    const rows = [];

    // 1) User
    const userLabel = isHr 
      ? (document.querySelector('#hr-user-title span')?.textContent || '')
      : (typeof currentUserId !== 'undefined' ? currentUserId : '');

    // 2) Worked & Overtime
    const workedEl   = container.querySelector('#worked-hours-month');
    const overtimeEl = container.querySelector('#overtime-month');
    const workedMonth   = workedEl?.textContent.trim() || '';
    const overtimeMonth = overtimeEl?.textContent.trim() || '';

    // 3) Daily working time
    const dailyMin = getEffectiveDailyMin(isHr ? container : null);
    const dailyHm = dailyMin != null ? minToHm(dailyMin) : '';

    rows.push([t('timesheet', 'User'), userLabel]);
    rows.push([t('timesheet', 'Worked Hours'), workedMonth]);
    rows.push([t('timesheet', 'Overtime'), overtimeMonth]);
    rows.push([t('timesheet', 'Daily working time'), dailyHm]);
    rows.push([]); // empty row

    // 4) Table Header
    const headerRow = [
      t('timesheet', 'Date'),
      "",
      t('timesheet', 'Status'),
      t('timesheet', 'Start'),
      t('timesheet', 'Break (min)'),
      t('timesheet', 'End'),
      t('timesheet', 'Duration'),
      t('timesheet', 'Difference'),
      t('timesheet', 'Comment'),
      t('timesheet', 'Warning')
    ];
    rows.push(headerRow);

    // 5) Table Rows
    const body = table.querySelector('tbody');
    if (body) {
      body.querySelectorAll('tr').forEach(tr => {
        const cols = Array.from(tr.cells).map(td => {
          const input = td.querySelector('input, textarea');
          if (input) {
            return input.value;
          }
          return td.textContent.trim();
        });
        rows.push(cols);
      });
    }

    const csv = toCsv(rows);

    const refMonth = isHr ? hrCurrentMonth : currentMonth;
    const year = refMonth.getFullYear();
    const month = String(refMonth.getMonth() + 1).padStart(2, '0');
    const monthStr = `${year}-${month}`;

    const baseName = isHr ? (userLabel || 'employee') : (typeof currentUserId !== 'undefined' ? currentUserId : 'me');

    const blob = new Blob(['\uFEFF', csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `${t('timesheet', 'timesheet')}-${baseName}-${monthStr}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  /**
  * API Calls 
  */

  async function api(path, options = {}) {
    // Wrapper for fetch calls to the Timesheet app API, automatically adds base URL and headers
    const url = OC.generateUrl(`/apps/timesheet${path}`); // generates correct base URL for app routes
    const res = await fetch(url, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'requesttoken': token, // include CSRF token in header
        ...(options.headers || {})
      }
    });
    if (!res.ok) {
      const errText = await res.text().catch(() => res.statusText);
      throw new Error(errText || `HTTP ${res.status}`);
    }
    return res.json().catch(() => null); // parse JSON, return null if no JSON body
  }

  async function loadUserConfig(uid) {
    // Load config (dailyMin, state) for user UID and update global userConfig and form fields
    try {
      const cfg = await api(`/api/hr/config/${uid}`);
      const minutes = pickDailyMin(cfg);
      userConfig = { ...cfg, dailyMin: minutes, workMinutes: minutes };
      if (minutes != null) {
        setConfigInputs(minutes, cfg?.state ?? null);
      } else if (cfg?.state != null) {
        setConfigInputs(null, cfg.state);
      }
    } catch (error) {
      console.warn('⚠️ Konnte Benutzerkonfiguration nicht laden:', error);
    }
  }

  async function loadHrUserList() {
    // Load list of all users (for HR view) and populate the user list in the UI
    if (!userListEl) return;
    userListEl.innerHTML = '';  // clear current list

    try {
      const users = await api('/api/hr/users');
      const frag = document.createDocumentFragment();
      
      users.forEach(({ id, name }) => {
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

        // Details (Soll, Überstunden, letzter Eintrag, Warnung) nachladen
        (async () => {
          try {
            const [cfg, overtime, lastDateStr] = await Promise.all([
              api(`/api/hr/config/${encodeURIComponent(id)}`),
              api(`/api/overtime/summary?user=${encodeURIComponent(id)}`),
              fetchLastEntryDate(id)
            ]);

            // Sollzeit aus Config (hr-user-target)
            const dailyMinutes = pickDailyMin(cfg) ?? 480;
            const targetCell = tr.querySelector('.hr-user-target');
            if (targetCell) {
              targetCell.textContent = minToHm(dailyMinutes);
            }

            // Gesamt-Überstunden (hr-user-balance)
            let overtimeMinutes = 0;
            const balanceCell = tr.querySelector('.hr-user-balance');
            if (balanceCell && overtime) {
              overtimeMinutes = overtime.overtimeMinutes ?? 0;
              balanceCell.textContent = minToHm(overtimeMinutes);
            }

            // Letzter Eintrag (Datum)
            const lastCell = tr.querySelector('.hr-user-last-entry');
            if (lastCell && lastDateStr) {
              const d = new Date(lastDateStr);
              lastCell.textContent = formatDate(d);
            }

            // Tage seit letztem Eintrag + Fehlermeldung
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
              if (diffDays >= 14) errors.push(t('timesheet', 'No entry for more than 14 days'));
            } else {
              errors.push(t('timesheet', 'No entry for more than 14 days'));
            }

            if (typeof overtimeMinutes === 'number') {
              if (overtimeMinutes > 600) {
                errors.push(t('timesheet', 'Too much overtime'));
              } else if (overtimeMinutes < -600) {
                errors.push(t('timesheet', 'Too many negative hours'));
              }
            }

            if (errorCell) errorCell.textContent = errors.join(', ');
          } catch (error) {
            console.warn(`⚠️ Konnte HR-Daten für User ${id} nicht laden:`, error);
          }
        })();
      });

      userListEl.appendChild(frag);
    } catch (error) {
      console.warn('⚠️ Konnte HR-Benutzerliste nicht laden:', error);
    }
  }

  async function getHolidays(year, state) {
    const cacheKey = `${state}_${year}`;
    if (holidayCache.has(cacheKey)) {
      return holidayCache.get(cacheKey);
    }
    try {
      const data = await api(`/api/holidays?year=${year}&state=${encodeURIComponent(state)}`);
      if (data && typeof data === 'object') {
        holidayCache.set(cacheKey, data);
        return data;
      }
    } catch (e) {
      console.warn(`⚠️ Feiertage konnten nicht geladen werden für ${year} ${state}:`, e);
    }
    // Fallback: keine Feiertage
    const empty = {};
    holidayCache.set(cacheKey, empty);
    return empty;
  }

  /**
  * DOM Rendering & Update Functions 
  */

  function createEntryRow(dateObj, entry, holidayMap = {}, dailyMin = null) {
    const dateStr    = toLocalIsoDate(dateObj);
    const dayIndex   = dateObj.getDay();
    const isHoliday  = Object.prototype.hasOwnProperty.call(holidayMap, dateStr);
    const isWeekend  = (dayIndex === 0 || dayIndex === 6);
    const statusText = isHoliday ? t('timesheet', 'Holiday') : (isWeekend ? t('timesheet', 'Weekend') : '');

    const startMin   = entry?.startMin ?? null;
    const endMin     = entry?.endMin ?? null;
    const brkMin     = entry?.breakMinutes ?? 0;
    const durMin     = (startMin != null && endMin != null) ? Math.max(0, endMin - startMin - brkMin) : null;
    const diffMin    = (durMin != null && dailyMin != null) ? (durMin - dailyMin) : null;
    const warning    = checkRules({ startMin, endMin, breakMinutes: brkMin }, dateStr, holidayMap);

    const startStr   = startMin != null ? minToHm(startMin) : '';
    const endStr     = endMin   != null ? minToHm(endMin)   : '';
    const breakStr   = String(brkMin ?? 0);
    const commentStr = entry?.comment ?? '';
    const durStr     = minToHm(durMin);
    const diffStr    = minToHm(diffMin);

    const tr = document.createElement('tr');
    tr.dataset.date = dateStr;
    if (entry?.id) {
      tr.dataset.id = entry.id;
    }

    tr.dataset.savedStart   = startStr;
    tr.dataset.savedEnd     = endStr;
    tr.dataset.savedBreak   = breakStr;
    tr.dataset.savedComment = commentStr;

    if (isHoliday || isWeekend) {
      tr.classList.add('is-weekend-row');
    }

    // Highlight heute
    const today = new Date();
    if (dateObj.getDate() === today.getDate() &&
        dateObj.getMonth() === today.getMonth() &&
        dateObj.getFullYear() === today.getFullYear()) {
      tr.classList.add('ts-today');
      tr.scrollIntoView({ block: 'center' });
    }

    tr.innerHTML = `
      <td>${formatDate(dateObj)}</td>
      <td>${days[dayIndex]}</td>
      <td class="ts-status ${isWeekend ? 'is-weekend' : ''}">${statusText}</td>
      <td><input type="time" class="startTime" value="${startStr}"></td>
      <td><input type="number" class="breakMinutes" value="${breakStr}" min="0"></td>
      <td><input type="time" class="endTime" value="${endStr}"></td>
      <td class="ts-duration">${durStr}</td>
      <td class="ts-diff">${diffStr}</td>
      <td><textarea class="commentInput">${commentStr}</textarea></td>
      <td class="ts-warn">${warning}</td>
    `;
    return tr;
  }

  async function loadUserEntries(userId = null, date = new Date()) {
    // Load entries for the given month (date) and user (null = current user). Then render the entries table.
    const { from, to } = getMonthRange(date);
    const fromStr = from.toISOString().slice(0, 10);
    const toStr   = to.toISOString().slice(0, 10);
    const query   = userId
      ? `/api/entries?user=${encodeURIComponent(userId)}&from=${fromStr}&to=${toStr}`
      : `/api/entries?from=${fromStr}&to=${toStr}`;
    const entries = await api(query).catch(error => {
      console.error('❌ Laden der Einträge fehlgeschlagen:', error);
      return [];
    });

    // Determine which table body to fill (current user vs HR user)
    const body = userId ? hrUserBody : tsBody;
    if (!body) return;
    body.innerHTML = '';  // clear existing entries

    // Map entries by date string for quick lookup
    const entryMap = {};
    entries.forEach(e => { entryMap[e.workDate] = e; });

    const year = from.getFullYear();

    let stateCode;
    if (userId) {
      const hrStateInput = document.querySelector('#tab-hr .config-state');
      stateCode = (hrStateInput?.value || 'BY');
    } else {
      const mineStateInput = document.querySelector('#tab-mine .config-state');
      stateCode = (mineStateInput?.value || userConfig?.state || 'BY');
    }

    const holidayMap = await getHolidays(year, stateCode);

    const container = userId ? document.getElementById('hr-user-entries') : null;
    const dailyMin = getEffectiveDailyMin(container);

    // Build table rows for each day of the month
    const frag = document.createDocumentFragment();
    for (let d = new Date(from); d <= to; d.setDate(d.getDate() + 1)) {
      const dateKey = toLocalIsoDate(d);
      const entry   = entryMap[dateKey];
      const row     = createEntryRow(new Date(d), entry, holidayMap, dailyMin);
      frag.appendChild(row);
    }
    body.appendChild(frag);

    // Update monthly total and overtime display after rendering
    const firstRow = body.querySelector('tr');
    if (firstRow) updateWorkedHours(firstRow);
  }

  function getEffectiveDailyMin(contextRoot) {
    const cfgMin = pickDailyMin(userConfig); // Fallback
    let inputMin = null;
    if (contextRoot) {
      // HR-Ansicht: nimm das Feld im HR-Container
      const input = contextRoot.querySelector('.config-daily-min');
      if (input) inputMin = hmToMin(input.value || '');
    } else {
      // Meine Zeiten: nimm das Feld im eigenen Tab
      const input = document.getElementById('config-daily-min-mine');
      if (input) inputMin = hmToMin(input.value || '');
    }
    return inputMin ?? cfgMin ?? 480;
  }

  function updateWorkedHours(anyRow) {
    // Calculate total worked minutes and overtime for the month in the table containing `anyRow`.
    const tbody = anyRow.closest('tbody');
    if (!tbody) return;
    let totalMinutes = 0;
    let workedDays   = 0;
    tbody.querySelectorAll('tr').forEach(row => {
      const durText = row.querySelector('.ts-duration')?.textContent.trim();
      const durMin  = hmToMin(durText);
      if (durMin && durMin > 0) {
        totalMinutes += durMin;
        workedDays++;
      }
    });
    // Decide which set of summary elements to update (HR view or personal view)
    const container = anyRow.closest('#hr-user-entries'); // if this row is in HR section
    const root = container || document;
    const workedEl   = root.querySelector('#worked-hours-month');
    const overtimeEl = root.querySelector('#overtime-month');
    const dailyMin   = getEffectiveDailyMin(container);
    const overtime   = totalMinutes - (workedDays * dailyMin);
    if (workedEl)   workedEl.textContent   = minToHm(totalMinutes);
    if (overtimeEl) overtimeEl.textContent = minToHm(overtime);
  }

  async function refreshOvertimeTotal(userId = null, container = document) {
    try {
      const uid = userId || currentUserId;
      if (!uid) return;

      const overtimeTotalEl = container.querySelector('#overtime-total');
      if (!overtimeTotalEl) return;

      const data = await api(`/api/overtime/summary?user=${encodeURIComponent(uid)}`);

      const minutes = data?.overtimeMinutes ?? 0;
      overtimeTotalEl.textContent = minToHm(minutes);
    } catch (error) {
      console.error('❌ Laden der Gesamt-Überstunden fehlgeschlagen:', error);
    }
  }

  function updateMonthDisplay() {
    // Update the month label for the personal view
    const labelEl = document.getElementById('month-display');
    if (labelEl) {
      labelEl.textContent = currentMonth.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });
    }
  }

  function updateHrMonthDisplay() {
    // Update the month label for the HR view
    const labelEl = document.getElementById('hr-month-display');
    if (labelEl) {
      labelEl.textContent = hrCurrentMonth.toLocaleDateString('de-DE', { month: 'long', year: 'numeric' });
    }
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

    await api(`/api/entries/${encodeURIComponent(entryId)}`, { method: 'DELETE' });

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
    const uid = isHr ? document.querySelector('#hr-user-title span')?.textContent : currentUserId;
    if (uid) await refreshOvertimeTotal(uid, isHr ? document.getElementById('tab-hr') : document
    );
  }

  async function saveRowIfNeeded(row) {
    // Parallel-Saves auf derselben Zeile verhindern
    if (row.dataset.saving === '1') return;

    const isHr = !!row.closest('#hr-user-entries');

    const startInput   = row.querySelector('.startTime');
    const endInput     = row.querySelector('.endTime');
    const breakInput   = row.querySelector('.breakMinutes');
    const commentInput = row.querySelector('.commentInput');
    const warnCell     = row.querySelector('.ts-warn');
    const durCell      = row.querySelector('.ts-duration');
    const diffCell     = row.querySelector('.ts-diff');

    if (!startInput || !endInput || !breakInput) return;

    const startVal = (startInput.value || '').trim();
    const endVal   = (endInput.value   || '').trim();
    const breakMin = parseInt(breakInput.value || '0', 10);
    const comment  = commentInput ? commentInput.value : '';

    const hasStart        = !!startVal;
    const hasEnd          = !!endVal;
    const hasBothTimes    = hasStart && hasEnd;
    const hasAnyTime      = hasStart || hasEnd;
    const commentNonEmpty = comment.trim().length > 0;

    const savedStart   = row.dataset.savedStart   || '';
    const savedEnd     = row.dataset.savedEnd     || '';
    const savedBreak   = row.dataset.savedBreak   != null ? parseInt(row.dataset.savedBreak, 10) : 0;
    const savedComment = row.dataset.savedComment ?? '';

    // Wenn sich nichts geändert hat → nicht speichern
    if (startVal === savedStart && endVal === savedEnd && breakMin === savedBreak && comment === savedComment) {
      if (!hasAnyTime && !commentNonEmpty) {
        if (warnCell) warnCell.textContent = '';
        if (durCell)  durCell.textContent  = '--:--';
        if (diffCell) diffCell.textContent = '--:--';
      }
      return;
    };

    const workDate = row.dataset.date;
    if (!workDate) return;

    const hasId = !!row.dataset.id;

    // Eintrag + Zeit & Kommentar leer → Auto-Delete
    if (!hasAnyTime && !commentNonEmpty) {
      if (hasId) {
        row.dataset.saving = '1';
        try {
          await deleteEntryForRow(row);
        } catch (error) {
          console.error('❌ Auto-Delete failed:', error);
        } finally {
          delete row.dataset.saving;
        }
      }
      return;
    }

    // Nur Kommentar vorhanden, Zeit leer → nichts tun
    if (!hasBothTimes && commentNonEmpty) {
      if (warnCell) warnCell.textContent = t('timesheet', 'Time missing');
      return;
    }

    if (!hasBothTimes && !commentNonEmpty) {
      if (warnCell) warnCell.textContent = t('timesheet', 'Time incomplete');
      return;
    }
    
    const payload = {
      workDate,
      start:        startVal,
      end:          endVal,
      breakMinutes: breakMin,
      comment
    };

    const startMin = hmToMin(payload.start);
    const endMin   = hmToMin(payload.end);
    const duration = (startMin != null && endMin != null) ? Math.max(0, endMin - startMin - payload.breakMinutes) : null;

    const dateStr = workDate;
    let stateCode;
    if (isHr) {
      const hrStateInput = document.querySelector('#tab-hr .config-state');
      stateCode = (hrStateInput?.value || 'BY');
    } else {
      const mineStateInput = document.querySelector('#tab-mine .config-state');
      stateCode = (mineStateInput?.value || userConfig?.state || 'BY');
    }
    const holidayMap = holidayCache.get(`${stateCode}_${dateStr.slice(0, 4)}`) || {};

    const baseDailyMin = getEffectiveDailyMin(isHr ? document.getElementById('hr-user-entries') : null);
    const diffMin      = (duration != null && baseDailyMin != null) ? (duration - baseDailyMin) : null;

    if (warnCell) warnCell.textContent = checkRules({ startMin, endMin, breakMinutes: payload.breakMinutes }, dateStr, holidayMap);
    if (durCell) durCell.textContent = minToHm(duration);
    if (diffCell) diffCell.textContent = minToHm(diffMin);

    row.dataset.saving = '1';

    try {
      let savedEntry;
      if (hasId) {
        // Update
        savedEntry = await api(`/api/entries/${encodeURIComponent(row.dataset.id)}`, {
          method: 'PUT',
          body: JSON.stringify(payload)
        });
      } else {
        // Insert
        const targetUserId = isHr ? document.querySelector('#hr-user-title span')?.textContent : null;
        const createPath = (isHr && targetUserId) ? `/api/entries?user=${encodeURIComponent(targetUserId)}` : `/api/entries`;
        savedEntry = await api(createPath, {
          method: 'POST',
          body: JSON.stringify(payload)
        });

        if (savedEntry?.id) row.dataset.id = savedEntry.id;
      }

      // Saved-State aktualisieren
      row.dataset.savedStart   = startVal;
      row.dataset.savedEnd     = endVal;
      row.dataset.savedBreak   = String(breakMin);
      row.dataset.savedComment = comment;

      // Monatliche Summen & Gesamtüberstunden aktualisieren
      updateWorkedHours(row);

      const uid = isHr ? document.querySelector('#hr-user-title span')?.textContent : currentUserId;
      if (uid) await refreshOvertimeTotal(uid, isHr ? document.getElementById('tab-hr') : document);

      // Visuelles Feedback
      showRowSavedFeedback(row);
    } catch (error) {
      console.error('❌ Auto-Save failed:', error);
    } finally {
      delete row.dataset.saving;
    }
  }

  /** 
  * Event Handlers 
  */

  // Month navigation buttons (personal view)
  $('#month-prev')?.addEventListener('click', () => {
    currentMonth.setMonth(currentMonth.getMonth() - 1);
    updateMonthDisplay();
    loadUserEntries(null, currentMonth);
  });
  $('#month-next')?.addEventListener('click', () => {
    currentMonth.setMonth(currentMonth.getMonth() + 1);
    updateMonthDisplay();
    loadUserEntries(null, currentMonth);
  });

  // Month navigation buttons (HR view)
  $('#hr-month-prev')?.addEventListener('click', () => {
    hrCurrentMonth.setMonth(hrCurrentMonth.getMonth() - 1);
    updateHrMonthDisplay();
    const uid = hrUserTitle?.querySelector('span')?.textContent;
    if (uid) loadUserEntries(uid, hrCurrentMonth);
  });
  $('#hr-month-next')?.addEventListener('click', () => {
    hrCurrentMonth.setMonth(hrCurrentMonth.getMonth() + 1);
    updateHrMonthDisplay();
    const uid = hrUserTitle?.querySelector('span')?.textContent;
    if (uid) loadUserEntries(uid, hrCurrentMonth);
  });

  // Click handler for HR user selection (when an HR clicks on a user from the list)
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.hr-load-user');
    if (!btn) return;
    const userId = btn.dataset.user;
    if (!userId) return;

    // Hide other HR sections and reset to initial state for new selection
    const tabHrSection = document.getElementById('tab-hr');
    
    const hrConfigRow     = tabHrSection?.querySelector('.hr-config-row');
    const hrDailyMinInput = hrConfigRow?.querySelector('.config-daily-min');
    const hrStateInput    = hrConfigRow?.querySelector('.config-state');

    if (tabHrSection) {
      tabHrSection.querySelectorAll('.ts-hr-section').forEach(sec => sec.style.display = 'none');
    }

    hrCurrentMonth = new Date(); // reset to current month for new user
    updateHrMonthDisplay();

    // Reset config inputs to default (8h and blank state) until real data is loaded
    if (hrDailyMinInput) hrDailyMinInput.value = '08:00';
    if (hrStateInput)    hrStateInput.value = '';
    // Try to load the selected user's config (if fails, defaults remain)
    try {
      const cfg = await api(`/api/hr/config/${userId}`);
      const min = (typeof cfg.dailyMin === 'number') ? cfg.dailyMin : 480;
      const state = (typeof cfg.state === 'string') ? cfg.state : '';

      if (hrDailyMinInput) hrDailyMinInput.value = minToHm(min);
      if (hrStateInput)    hrStateInput.value = state;
    } catch {
      // If loading config failed, keep default 08:00 and empty state
      if (hrDailyMinInput) hrDailyMinInput.value = '08:00';
      if (hrStateInput)    hrStateInput.value = '';
    }
    // Load and display the selected user's entries for the current month
    await loadUserEntries(userId, hrCurrentMonth);
    await refreshOvertimeTotal(userId, document.getElementById('tab-hr'));

    if (hrUserEntries) hrUserEntries.style.display = 'block';
    if (hrUserTitle) {
      const span = hrUserTitle.querySelector('span');
      if (span) span.textContent = userId;
    }
  });

  // "Back" button in HR view to return to user list
  document.getElementById('hr-back-button')?.addEventListener('click', () => {
    const tabHrSection = document.getElementById('tab-hr');
    if (!tabHrSection) return;
    // Hide the user entries section and show the user list and violations sections
    tabHrSection.querySelectorAll('.ts-hr-section').forEach(sec => sec.style.display = 'none');
    const listSection = tabHrSection.querySelector('#hr-userlist')?.closest('.ts-hr-section');
    const violSection = tabHrSection.querySelector('#hr-violations-body')?.closest('.ts-hr-section');
    if (listSection) listSection.style.display = 'block';
    if (violSection) violSection.style.display = 'block';
    if (hrUserEntries) hrUserEntries.style.display = 'none';
  });

  // Tab switching (between "Meine Zeiten" and "HR" views)
  document.querySelectorAll('.ts-tab').forEach(tabButton => {
    tabButton.addEventListener('click', () => {
      // Activate the clicked tab and deactivate others
      document.querySelectorAll('.ts-tab').forEach(btn => btn.classList.remove('active'));
      document.querySelectorAll('.ts-tabview').forEach(view => view.classList.remove('active'));
      tabButton.classList.add('active');
      const targetView = document.getElementById(`tab-${tabButton.dataset.tab}`);
      if (targetView) targetView.classList.add('active');
      // If HR tab is selected, load the user list
      if (tabButton.dataset.tab === 'hr') {
        loadHrUserList();
      }
    });
  });

  // Save current user's config (dailyMin and state) – accessible to both normal user and HR (for their own config)
  saveConfigBtns.forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!currentUserId) return;
    
      const container = btn.closest('.hr-config-row') || document;
      const dailyInput = container.querySelector('.config-daily-min') || dailyMinInputs[0];
      const stateInput = container.querySelector('.config-state') || stateInputs[0];

      const timeStr = dailyInput?.value || '';
      const state   = stateInput?.value || '';
      const minutes = hmToMin(timeStr) ?? 480;
      
      btn.disabled = true;

      try {
        await api(`/api/hr/config/${currentUserId}`, {
          method: 'PUT',
          body: JSON.stringify({ dailyMin: minutes, state })
        });
    
        // Update global userConfig and recalc overtime with the new settings
        userConfig = { ...(userConfig || {}), dailyMin: minutes, workMinutes: minutes, state };
        setConfigInputs(minutes, state);

        const firstRow = tsBody?.querySelector('tr') || hrUserBody?.querySelector('tr');
        if (firstRow) updateWorkedHours(firstRow);
    
        await refreshOvertimeTotal(currentUserId);
      } catch (error) {
        console.error('❌ Speichern der Konfiguration fehlgeschlagen:', error);
      } finally {
        btn.disabled = false;
      }
    });
  });

  // Live-update overtime when User adjusts the daily-min input for the selected user
  dailyMinInputs.forEach(input => {
    input.addEventListener('input', () => {
      const inMine = !!input.closest('#tab-mine');
      const inHr =   !!input.closest('#tab-hr');

      if (inMine && tsBody) {
        const firstRow = tsBody.querySelector('tr');
        if (firstRow) updateWorkedHours(firstRow);
      }

      if (inHr && hrUserBody) {
        const firstHrRow = hrUserBody.querySelector('tr');
        if (firstHrRow) updateWorkedHours(firstHrRow);
      }
    });
  });

  document.addEventListener('blur', async (e) => {
    const el = e.target;
    if (!(el instanceof HTMLElement)) return;

    if (
      !el.classList.contains('startTime') &&
      !el.classList.contains('endTime') &&
      !el.classList.contains('breakMinutes') &&
      !el.classList.contains('commentInput')
    ) {
      return;
    }

    const row = el.closest('tr');
    if (!row) return;

    await saveRowIfNeeded(row);
  }, true);

  document.addEventListener('keydown', async (e) => {
    if (e.key !== 'Enter' || e.shiftKey) return;

    const el = e.target;
    if (
      !el.classList.contains('startTime') &&
      !el.classList.contains('endTime') &&
      !el.classList.contains('breakMinutes') &&
      !el.classList.contains('commentInput')
    ) {
      return;
    }

    e.preventDefault();
    const row = el.closest('tr');
    if (!row) return;

    await saveRowIfNeeded(row);
  });

  document.getElementById('export-mine-csv')?.addEventListener('click', () => {
    exportCurrentViewToCsv(false);
  });

  document.getElementById('export-hr-csv')?.addEventListener('click', () => {
    exportCurrentViewToCsv(true);
  });

  /**
  * Initialization on page load
  */

  updateMonthDisplay();
  updateHrMonthDisplay();

  const cfgReady = currentUserId ? loadUserConfig(currentUserId) : Promise.resolve();

  await loadUserEntries(null, currentMonth);
  await cfgReady;
  await refreshOvertimeTotal();

  const initRow = document.querySelector('#ts-body tr');
  if (initRow) updateWorkedHours(initRow);
})();