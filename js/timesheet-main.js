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
      console.warn('⚠️ Could not determine currentUserId:', error);
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

  const userListEl              = document.getElementById('hr-userlist'); // list of users for HR view
  const hrStatsTotalEl          = document.getElementById('hr-stat-total-hours'); // total hours element in HR view
  const hrStatsOvertimeEl       = document.getElementById('hr-stat-total-overtime'); // total overtime element in HR view
  const hrStatsNOvertimeEl      = document.getElementById('hr-stat-employees-overtime'); // number of overtime entries element in HR view
  const hrStatsMinusOvertimeEl  = document.getElementById('hr-stat-total-negative'); // total negative hours element in HR view
  const hrStatsNMinusOvertimeEl = document.getElementById('hr-stat-employees-negative'); // number of negative overtime entries element in HR view
  const hrStatsSumOvertimeEl    = document.getElementById('hr-stat-sum-overtimes'); // overtime sum element in HR view

  const dailyMinInputs = Array.from(document.querySelectorAll('.config-daily-min')); // all daily min inputs
  const stateInputs    = Array.from(document.querySelectorAll('.config-state')); // all state select inputs
  const saveConfigBtns = Array.from(document.querySelectorAll('.save-config-btn')); // all save config buttons

  // Short alias for querySelector (for convenience in this script)
  const $ = (sel) => document.querySelector(sel);

  // Timesheet row hover tracking
  let tsHoveredRow = null;
  const TS_ROW_SCOPE = '#tab-mine tbody tr, #tab-hr tbody tr, #hr-user-entries tbody tr';

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
    let sign = 1;
    str = str.trim();
    if (str.startsWith('-')) {
      sign = -1;
      str = str.slice(1);
    }
    const [h, m] = str.split(':').map(Number);
    if (Number.isNaN(h) || Number.isNaN(m)) return null;
    return sign * (h * 60 + m);
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
      console.error(`❌ Failed to load the last entry for ${userId}:`, error);
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

  function updateHrStats() {
    if (!userListEl) return;

    let totalMinutes = 0; // total worked minutes of all users
    let totalOvertimeMinutes = 0; // total overtime minutes of all users
    let totalNOvertime = 0; // number of users with overtime
    let totalMinusOvertimeMinutes = 0; // total negative overtime minutes of all users
    let totalNMinusOvertime = 0; // number of users with negative overtime
    userListEl.querySelectorAll('tr').forEach(tr => {
      let val = parseInt(tr.dataset.totalMinutesMonth || '0', 10);
      if (!Number.isNaN(val)) totalMinutes += val;
      
      val = hmToMin(tr.querySelector('.hr-user-balance')?.textContent || '0');
      if (!Number.isNaN(val) && val > 0) {
        totalOvertimeMinutes += val;
        totalNOvertime++;
      } 
      if (!Number.isNaN(val) && val < 0) {
        totalMinusOvertimeMinutes += val;
        totalNMinusOvertime++;
      } 
    });

    if (hrStatsTotalEl) hrStatsTotalEl.textContent = minToHm(totalMinutes);
    if (hrStatsOvertimeEl) hrStatsOvertimeEl.textContent = minToHm(totalOvertimeMinutes);
    if (hrStatsNOvertimeEl) hrStatsNOvertimeEl.textContent = String(totalNOvertime);
    if (hrStatsMinusOvertimeEl) hrStatsMinusOvertimeEl.textContent = minToHm(totalMinusOvertimeMinutes);
    if (hrStatsNMinusOvertimeEl) hrStatsNMinusOvertimeEl.textContent = String(totalNMinusOvertime);
    if (hrStatsSumOvertimeEl) hrStatsSumOvertimeEl.textContent = minToHm(totalOvertimeMinutes + totalMinusOvertimeMinutes);
  }

  function hasSelection() {
    const sel = window.getSelection?.();
    return !!sel && String(sel).trim() !== '';
  }

  function isTimesheetCellField(el) {
    if (!el || !el.classList) return false;
    return el.classList.contains('startTime')
      || el.classList.contains('endTime')
      || el.classList.contains('breakMinutes')
      || el.classList.contains('commentInput');
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
      console.warn('⚠️ Could not load user configuration', error);
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

            if (overtime) {
              // 1) Gesamt-Arbeitszeit des Monats
              const totalMinutes = overtime.totalMinutes ?? 0;
              tr.dataset.totalMinutesMonth = String(totalMinutes);

              // 2) Überstunden-Saldo (hr-user-balance)
              let overtimeMinutes = 0;
              const balanceCell = tr.querySelector('.hr-user-balance');
              if (balanceCell) {
                overtimeMinutes = overtime.overtimeMinutes ?? 0;
                balanceCell.textContent = minToHm(overtimeMinutes);
              }
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

            updateHrStats();
          } catch (error) {
            console.warn(`⚠️ Failed to load HR data for user ${id}:`, error);
          }
        })();
      });

      userListEl.appendChild(frag);
    } catch (error) {
      console.warn('⚠️ Failed to load HR user list:', error);
    }
  }

  async function getHolidays(year, state) {
    if (state == null || state === '') {
      return {};
    }
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
      console.warn(`⚠️ Failed to load holidays for ${year} ${state}:`, e);
    }
    // Fallback: keine Feiertage
    const empty = {};
    holidayCache.set(cacheKey, empty);
    return empty;
  }

  function applyFixedRangeLabelWidth(kind) {
    const st = exportRanges[kind];
    if (!st.ui?.label) return;

    // Compute and cache fixed width for range label
    if (!st.fixedLabelwidthPx) {
      const style = window.getComputedStyle(st.ui.label);

      const meas = document.createElement('span');
      meas.style.position = 'absolute';
      meas.style.visibility = 'hidden';
      meas.style.whiteSpace = 'nowrap';
      meas.style.font = style.font;
      document.body.appendChild(meas);

      const year = new Date().getFullYear();
      const months = Array.from({ length: 12 }, (_, i) => new Date(year, i, 1));

      let max = 0;
      for (const m1 of months) {
        for (const m2 of months) {
          meas.textContent = `${monthLabel(m1)} – ${monthLabel(m2)}`;
          max = Math.max(max, meas.getBoundingClientRect().width);
        }
      }
      for (const m of months) {
        meas.textContent = monthLabel(m);
        max = Math.max(max, meas.getBoundingClientRect().width);
      }
      document.body.removeChild(meas);
      st.fixedLabelwidthPx = Math.ceil(max) + 8; // +8px padding
    }

    st.ui.label.style.display = 'inline-block';
    st.ui.label.style.textAlign = 'center';
    st.ui.label.style.width = `${st.fixedLabelwidthPx}px`;
    st.ui.label.style.whiteSpace = 'nowrap';
  }

  function resolveLocale() {
    try {
      if (window.OC && typeof window.OC.getLocale === 'function') return String(OC.getLocale());
      if (window.OC && typeof window.OC.getLanguage === 'function') return String(OC.getLanguage());
    } catch {}
    return document.documentElement.getAttribute('lang') || navigator.language || undefined;
  }

  function normalizeLocale(locale) {
    if (!locale) return undefined;
    return String(locale).replace('_', '-');
  }

  const USER_LOCALE = normalizeLocale(resolveLocale());

  function buildMonthFormatter() {
    try {
      return new Intl.DateTimeFormat(USER_LOCALE, { month: 'long', year: 'numeric' });
    } catch {
      return new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' });
    }
  }

  const MONTH_FMT = buildMonthFormatter();

  function toLocalMonthStr(dateObj) {
    const year  = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    return `${year}-${month}`;
  }

  function monthLabel(dateObj) {
    return MONTH_FMT.format(dateObj);
  }

  function firstOfMonth(dateObj) {
    return new Date(dateObj.getFullYear(), dateObj.getMonth(), 1);
  }

  function addMonths(dateObj, delta) {
    return new Date(dateObj.getFullYear(), dateObj.getMonth() + delta, 1);
  }

  const exportRanges = {
    mine: {
      armed: false,
      from: null,
      to: null,
      ui: null
    },
    hr: {
      armed: false,
      from: null,
      to: null,
      ui: null,
    },
  };

  function ensureExportRangeUi(kind) {
    const exportBtnId = (kind === 'hr') ? 'export-hr-xlsx' : 'export-mine-xlsx';
    const exportBtn = document.getElementById(exportBtnId);
    if (!exportBtn) return null;

    const uiId = `${exportBtnId}-range-ui`;
    let container = document.getElementById(uiId);
    if (container) {
      exportRanges[kind].ui = exportRanges[kind].ui || {
        container,
        fromPrev: container.querySelector('[data-action="from-prev"]'),
        fromNext: container.querySelector('[data-action="from-next"]'),
        toPrev:   container.querySelector('[data-action="to-prev"]'),
        toNext:   container.querySelector('[data-action="to-next"]'),
        label:    container.querySelector('.ts-export-range-label'),
      };
      return exportRanges[kind].ui;
    }

    container = document.createElement('span');
    container.id = uiId;
    container.style.display = 'none';
    container.style.alignItems = 'center';
    container.style.gap = '6px';
    container.style.marginLeft = '8px';

    const makeBtn = (text, title, action) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = text;
      b.title = title;
      b.dataset.action = action;
      b.className = 'primary';
      b.style.padding = '2px 8px';
      b.style.lineHeight = '1.2';
      return b;
    };

    const fromPrev = makeBtn('«', t('timesheet', 'Start month: previous'), 'from-prev');
    const fromNext = makeBtn('»', t('timesheet', 'Start month: next'), 'from-next');

    const label = document.createElement('span');
    label.className = 'ts-export-range-label';
    label.style.whiteSpace = 'nowrap';
    label.style.padding = '0 4px';

    const toPrev = makeBtn('«', t('timesheet', 'End month: previous'), 'to-prev');
    const toNext = makeBtn('»', t('timesheet', 'End month: next'), 'to-next');

    container.appendChild(fromPrev);
    container.appendChild(fromNext);
    container.appendChild(label);
    container.appendChild(toPrev);
    container.appendChild(toNext);

    exportBtn.insertAdjacentElement('afterend', container);
    
    const ui = { container, fromPrev, fromNext, toPrev, toNext, label };
    exportRanges[kind].ui = ui;
    
    applyFixedRangeLabelWidth(kind);
    
    const shift = (which, delta) => {
      const st = exportRanges[kind];
      if (!st.from || !st.to) return;

      if (which === 'from') st.from = addMonths(st.from, delta);
      if (which === 'to')   st.to   = addMonths(st.to, delta);

      if (st.from > st.to) {
        if (which === 'from') st.to = firstOfMonth(st.from);
        else st.from = firstOfMonth(st.to);
      }

      renderExportRange(kind);
    };

    fromPrev.addEventListener('click', () => shift('from', -1));
    fromNext.addEventListener('click', () => shift('from', +1));
    toPrev.addEventListener('click', () => shift('to', -1));
    toNext.addEventListener('click', () => shift('to', +1));

    return ui;
  }

  function renderExportRange(kind) {
    const st = exportRanges[kind];
    const ui = st.ui || ensureExportRangeUi(kind);
    if (!ui || !st.from || !st.to) return;

    ui.container.style.display = 'inline-flex';

    const sameMonth = st.from.getFullYear() === st.to.getFullYear() && st.from.getMonth() === st.to.getMonth();

    ui.label.textContent = sameMonth ? monthLabel(st.from) : `${monthLabel(st.from)} – ${monthLabel(st.to)}`;
  }

  function resetExportRange(kind) {
    const st = exportRanges[kind];
    st.armed = false;
    st.from = null;
    st.to = null;
    if (st.ui?.container) st.ui.container.style.display = 'none';
  }

  function handleExportClick(kind) {
    const st = exportRanges[kind];

    if (!st.armed) {
      const base = (kind === 'hr') ? hrCurrentMonth : currentMonth;
      st.from = firstOfMonth(base);
      st.to   = firstOfMonth(base);
      st.armed = true;
      renderExportRange(kind);
      return;
    }

    if (!st.from || !st.to) {
      resetExportRange(kind);
      return;
    }

    const fromStr = toLocalMonthStr(st.from);
    const toStr   = toLocalMonthStr(st.to);

    let url = `/apps/timesheet/api/entries/export-xlsx?from=${encodeURIComponent(fromStr)}&to=${encodeURIComponent(toStr)}`;
    if (kind === 'hr') {
      const uid = hrUserTitle?.querySelector('span')?.textContent?.trim();
      if (uid) url += `&user=${encodeURIComponent(uid)}`;
    }

    resetExportRange(kind);
    window.location.href = OC.generateUrl(url);
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
      <td><input type="number" class="breakMinutes" value="${breakStr}"></td>
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
      console.error('❌ Failed to load entries:', error);
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
      stateCode = (hrStateInput?.value);
    } else {
      const mineStateInput = document.querySelector('#tab-mine .config-state');
      stateCode = (mineStateInput?.value || userConfig?.state);
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
      console.error('❌ Failed to load total overtime hours:', error);
    }
  }

  function updateMonthDisplay() {
    const labelEl = document.getElementById('month-display');
    if (labelEl) labelEl.textContent = monthLabel(currentMonth);
  }

  function updateHrMonthDisplay() {
    const labelEl = document.getElementById('hr-month-display');
    if (labelEl) labelEl.textContent = monthLabel(hrCurrentMonth);
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
      stateCode = (hrStateInput?.value);
    } else {
      const mineStateInput = document.querySelector('#tab-mine .config-state');
      stateCode = (mineStateInput?.value || userConfig?.state);
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

  // Click handler for month label to jump back to current month (personal view)
  $('#month-display')?.addEventListener('click', () => {
    const today = new Date();
    if (currentMonth.getFullYear() === today.getFullYear() && currentMonth.getMonth() === today.getMonth()) {
      return; // already current month
    }
    currentMonth = today;
    updateMonthDisplay();
    loadUserEntries(null, currentMonth);
  });
  // Click handler for month label to jump back to current month (HR view)
  $('#hr-month-display')?.addEventListener('click', () => {
    const today = new Date();
    if (hrCurrentMonth.getFullYear() === today.getFullYear() && hrCurrentMonth.getMonth() === today.getMonth()) {
      return; // already current month
    }
    hrCurrentMonth = today;
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

    // Hide the user entries section and show the user list
    tabHrSection.querySelectorAll('.ts-hr-section').forEach(sec => {
      if (sec.id === 'hr-user-entries') {
        sec.style.display = 'none';
      } else {
        sec.style.display = '';
      }
    });
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

  document.addEventListener('pointerover', (e) => {
    const row = e.target.closest(TS_ROW_SCOPE);
    if (row) tsHoveredRow = row;
  });

  document.addEventListener('pointerout', (e) => {
    const row = e.target.closest(TS_ROW_SCOPE);
    if (row && tsHoveredRow === row) tsHoveredRow = null;
  });

  // Copy handler to copy the currently hovered row as TSV to clipboard
  document.addEventListener('copy', (e) => {
    const el = document.activeElement;

    // Cell Copy: if focused element is input/textarea in a timesheet row, copy its value only
    const tag = (el?.tagName || '').toUpperCase();
    const isInputLike = tag === 'INPUT' || tag === 'TEXTAREA';
    if (isInputLike && isTimesheetCellField(el)) {
      if (typeof el?.selectionStart === 'number'
        && typeof el?.selectionEnd === 'number'
        && el.selectionEnd > el.selectionStart
      ) return; // user has selected text in input/textarea -> don't interfere
      if (!e.clipboardData) return; // no clipboardData -> can't set data

      e.clipboardData.setData('text/plain', String(el.value ?? ''));
      e.preventDefault();
      window.OC?.Notification?.showTemporary(t('timesheet', 'Copied to clipboard'));
      return;
    }

    // Row Copy: if not in an input/textarea, copy the entire hovered row
    const inEditable = isInputLike || !!el?.isContentEditable;
    if (inEditable) return; // don't interfere with other editable elements

    if (!tsHoveredRow) return; // no row hovered -> nothing to copy
    if (hasSelection()) return; // user has selected text -> don't interfere
    if (!e.clipboardData) return; // no clipboardData -> can't set data

    const start = (tsHoveredRow.querySelector('.startTime')?.value || '').trim();
    const brk = (tsHoveredRow.querySelector('.breakMinutes')?.value || '').trim();
    const end = (tsHoveredRow.querySelector('.endTime')?.value || '').trim();
    let comment = (tsHoveredRow.querySelector('.commentInput')?.value || '').trim();
    comment = comment.replace(/\t/g, ' ').replace(/\r?\n/g, ' '); // remove tabs/newlines

    e.clipboardData.setData('text/plain', [start, brk, end, comment].join('\t'));
    e.preventDefault();
    window.OC?.Notification?.showTemporary(t('timesheet', 'Row copied to clipboard'));
  });

  // Save current user's config (dailyMin and state) – accessible to both normal user and HR (for their own config)
  saveConfigBtns.forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!currentUserId) return;
    
      const container = btn.closest('.hr-config-row') || document;
      const dailyInput = container.querySelector('.config-daily-min') || dailyMinInputs[0];
      const stateInput = container.querySelector('.config-state')     || stateInputs[0];

      const timeStr = dailyInput?.value || '';
      const state   = stateInput?.value || '';
      const minutes = hmToMin(timeStr) ?? 480;
      
      const inMine = !!btn.closest('#tab-mine');
      const inHr =   !!btn.closest('#tab-hr');

      let targetUserId = currentUserId;
      if (inHr) {
        const span = hrUserTitle?.querySelector('span');
        const selectedId = span?.textContent?.trim();
        if (selectedId) {
          targetUserId = selectedId;
        }
      }

      btn.disabled = true;

      try {
        await api(`/api/hr/config/${targetUserId}`, {
          method: 'PUT',
          body: JSON.stringify({ dailyMin: minutes, state })
        });
    
        if (targetUserId === currentUserId) {
          // If saving own config, also update the global userConfig
          userConfig = { ...(userConfig || {}), dailyMin: minutes, workMinutes: minutes, state };
          
          // Update userConfig and recalc overtime with the new settings
          userConfig = { ...(userConfig || {}), dailyMin: minutes, workMinutes: minutes, state };
          setConfigInputs(minutes, state);
          
          const firstRow = tsBody?.querySelector('tr');
          if (firstRow) updateWorkedHours(firstRow);

          await refreshOvertimeTotal(currentUserId);
        } else {
          // If HR saved another user's config, reload that user's entries with new settings
          const firstHrRow = hrUserBody?.querySelector('tr');
          if (firstHrRow) updateWorkedHours(firstHrRow);

          await refreshOvertimeTotal(targetUserId, document.getElementById('tab-hr'));
        }
      } catch (error) {
        console.error('❌ Failed to save configuration:', error);
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

  document.getElementById('export-mine-xlsx')?.addEventListener('click', () => handleExportClick('mine'));;
  document.getElementById('export-hr-xlsx')?.addEventListener('click', () => handleExportClick('hr'));

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