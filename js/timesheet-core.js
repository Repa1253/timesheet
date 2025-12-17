(function () {
  "use strict";

  window.Timesheet = window.Timesheet || {};
  const TS = window.Timesheet;

  TS.state = TS.state || {};
  const S = TS.state;

  // Application identifiers
  S.appName = "timesheet";
  S.token = (window.OC && OC.requestToken) ? OC.requestToken : null;

  // Current user ID
  S.currentUserId = (function getCurrentUserId() {
    try {
      if (window.OC && typeof OC.getCurrentUser === "function") {
        return OC.getCurrentUser()?.uid ?? null;
      }
      if (window.OC && OC.currentUser) {
        return OC.currentUser;
      }
    } catch (e) {
      console.warn("⚠️ Could not determine currentUserId:", e);
    }
    return null;
  })();

  S.userConfig = null;

  // Current month tracking
  S.currentMonth = new Date();
  S.hrCurrentMonth = new Date();

  // Holiday cache
  S.holidayCache = new Map();

  // Hovered row tracking for copy functionality
  S.tsHoveredRow = null;
  S.TS_ROW_SCOPE = '#tab-mine tbody tr, #tab-hr tbody tr, #hr-user-entries tbody tr';

  // DOM cache
  TS.dom = TS.dom || {};
  TS.dom.refresh = function refreshDomCache() {
    this.tsBody        = document.getElementById('ts-body');
    this.hrUserBody    = document.getElementById('hr-user-body');
    this.hrUserEntries = document.getElementById('hr-user-entries');
    this.hrUserTitle   = document.getElementById('hr-user-title');

    this.userListEl = document.getElementById('hr-userlist');

    this.hrStatsTotalEl          = document.getElementById('hr-stat-total-hours');
    this.hrStatsOvertimeEl       = document.getElementById('hr-stat-total-overtime');
    this.hrStatsNOvertimeEl      = document.getElementById('hr-stat-employees-overime');
    this.hrStatsMinusOvertimeEl  = document.getElementById('hr-stat-total-negative');
    this.hrStatsNMinusOvertimeEl = document.getElementById('hr-stat-employees-negative');
    this.hrStatsSumOvertimeEl    = document.getElementById('hr-stat-sum-overtimes');

    this.dailyMinInputs = Array.from(document.querySelectorAll('.config-daily-min'));
    this.stateInputs    = Array.from(document.querySelectorAll('.config-state'));
    this.saveConfigBtns = Array.from(document.querySelectorAll('.save-config-btn'));

    return this;
  };

  // Simple selectors
  TS.$  = (sel, root = document) => root.querySelector(sel);
  TS.$$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  // Notification utility
  TS.notify = (msg) => {
    try {
      if (window.OC?.Notification?.showTemporary) {
        OC.Notification.showTemporary(msg);
      } else {
        console.log(msg);
      }
    } catch (error) {
      console.log(msg);
    }
  };

  // Localization utilities
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

  // Month formatter
  function buildMonthFormatter() {
    try {
      return new Intl.DateTimeFormat(USER_LOCALE, { month: 'long', year: 'numeric' });
    } catch {
      return new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' });
    }
  }

  const MONTH_FMT = buildMonthFormatter();

  TS.util = TS.util || {};
  const U = TS.util;

  U.USER_LOCALE = USER_LOCALE;

  // Day keys
  const DAY_KEYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  // Day label by index
  U.dayLabel = (dayIndex) => {
    const key = DAY_KEYS[dayIndex] ?? '';
    return key ? t(S.appName, key) : '';
  };

  // "DD.MM.YYYY" format
  U.formatDate = (dateObj) => {
    const day   = String(dateObj.getDate()).padStart(2, '0');
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const year  = dateObj.getFullYear();
    return `${day}.${month}.${year}`;
  };

  // "YYYY-MM-DD" format
  U.toLocalIsoDate = (dateObj) => {
    const year  = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day   = String(dateObj.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  // Gets first and last date of month for given date
  U.getMonthRange = (date) => {
    const y = date.getFullYear();
    const m = date.getMonth();
    return { from: new Date(y, m, 1), to: new Date(y, m + 1, 0) };
  };

  // Formats minutes to "HH:MM" format
  U.minToHm = (min) => {
    if (min == null) return '--:--';
    const sign = min < 0 ? '-' : '';
    const absMin = Math.abs(min);
    const h = String(Math.floor(absMin / 60)).padStart(2, '0');
    const m = String(absMin % 60).padStart(2, '0');
    return `${sign}${h}:${m}`;
  };

  // Parses "HH:MM" format to minutes
  U.hmToMin = (str) => {
    if (!str) return null;
    let sign = 1;
    let s = String(str).trim();
    if (s.startsWith('-')) {
      sign = -1;
      s = s.slice(1);
    }
    const parts = s.split(':');
    if (parts.length !== 2) return null;
    const h = Number(parts[0]);
    const m = Number(parts[1]);
    if (Number.isNaN(h) || Number.isNaN(m)) return null;
    return sign * (h * 60 + m);
  };

  // Picks daily minimum minutes from config
  U.pickDailyMin = (cfg) => {
    if (!cfg) return null;
    if (Number.isFinite(cfg.dailyMin)) return cfg.dailyMin;
    if (Number.isFinite(cfg.workMinutes)) return cfg.workMinutes;
    if (typeof cfg.workMinutes === 'string') return U.hmToMin(cfg.workMinutes);
    return null;
  };

  // Checks timesheet entry against rules, returns issues as string
  U.checkRules = (entry, dateStr = null, holidayMap = {}) => {
    const start = entry.startMin ?? U.hmToMin(entry.start);
    const end   = entry.endMin ?? U.hmToMin(entry.end);
    if (start == null || end == null) return '';

    const brk = entry.breakMinutes ?? 0;
    const gross = end - start;
    const dur = Math.max(0, gross - brk);

    const issues = [];

    if (dur > 10 * 60) issues.push(t(S.appName, 'Above maximum time')); // over 10h work not allowed

    if (dur > 9 * 60 && brk < 45) { // over 9h work requires at least 45min break
      issues.push(t(S.appName, 'Break too short'));
    } else if (dur > 6 * 60 && brk < 30) { // over 6h work requires at least 30min break
      issues.push(t(S.appName, 'Break too short'));
    }

    if (dateStr) {
      const date = new Date(dateStr);
      const isSunday = date.getDay() === 0;
      const isHoliday = !!(holidayMap && holidayMap[dateStr]);

      if (isSunday) issues.push(t(S.appName, 'Sunday work not allowed'));
      if (isHoliday) issues.push(t(S.appName, 'Holiday work not allowed'));
    }

    return issues.join(', ');
  };

  // Shows visual feedback for saved row
  U.showRowSavedFeedback = (row) => {
    row.classList.add('ts-row-saved');
    setTimeout(() => row.classList.remove('ts-row-saved'), 1200);
  };

  // Sets the configuration input fields
  U.setConfigInputs = (minutes, state) => {
    const timeStr = minutes != null ? U.minToHm(minutes) : '';
    const dom = TS.dom;
    (dom.dailyMinInputs || []).forEach(input => { input.value = timeStr; });
    (dom.stateInputs || []).forEach(input => { input.value = state ?? ''; });
  };

  // Checks if there is any text selection
  U.hasSelection = () => {
    const sel = window.getSelection?.();
    return !!sel && String(sel).trim() !== '';
  };

  // Checks if element is one of the timesheet cell input fields
  U.isTimesheetCellField = (el) => {
    if (!el || !el.classList) return false;
    return el.classList.contains('startTime')
      || el.classList.contains('endTime')
      || el.classList.contains('breakMinutes')
      || el.classList.contains('commentInput');
  };

  // Parses break minutes input, returns minutes or null if invalid
  U.parseBreakMinutesInput = (raw) => {
    const s0 = String(raw ?? '').trim();
    if (s0 === '') return 0;

    let sign = 1;
    let s = s0;
    if (s.startsWith('-')) {
      sign = -1;
      s = s.slice(1).trim();
    }
    if (s === '') return 0;

    if (!s.includes(':')) {
      if (!/^\d+$/.test(s)) return null;
      return sign * Number(s);
    }

    const m = s.match(/^(\d+)\s*:\s*(\d+)$/);
    if (!m) return null;
    const h = Number(m[1]);
    const mm = Number(m[2]);
    if (!Number.isFinite(h) || !Number.isFinite(mm)) return null;
    return sign * (h * 60 + mm);
  };

  // "Month Year" format
  U.monthLabel = (dateObj) => MONTH_FMT.format(dateObj);

  // "YYYY-MM" format
  U.toLocalMonthStr = (dateObj) => {
    const year  = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    return `${year}-${month}`;
  };

  // Set to first day of month
  U.firstOfMonth = (dateObj) => new Date(dateObj.getFullYear(), dateObj.getMonth(), 1);

  // Add months, set to first day of month
  U.addMonths = (dateObj, delta) => new Date(dateObj.getFullYear(), dateObj.getMonth() + delta, 1);

  // API utility
  TS.api = async function api(path, options = {}) {
    const url = OC.generateUrl(`/apps/${S.appName}${path}`);
    const res = await fetch(url, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'requesttoken': S.token,
        ...(options.headers || {})
      }
    });
    
    if (!res.ok) {
      const errText = await res.text().catch(() => res.statusText);
      throw new Error(errText || `HTTP ${res.status}`);
    }
    return res.json().catch(() => null);
  };
})();