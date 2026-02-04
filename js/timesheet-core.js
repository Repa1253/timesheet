(function () {
  "use strict";

  window.Timesheet = window.Timesheet || {};
  const TS = window.Timesheet;

  TS.state = TS.state || {};
  const S = TS.state;

  // Application identifiers
  S.appName = "timesheet";
  function getRequestToken() {
    try {
      if (window.OC && OC.requestToken) return String(OC.requestToken);
    } catch {}
    return null;
  }
  S.token = getRequestToken();
  S.sessionExpiredNotified = false;

  const RULE_DEFAULTS = {
    breakShortMinutes: 30,
    breakShortHours: 6,
    breakLongMinutes: 45,
    breakLongHours: 9,
    maxHours: 10,
  };

  S.ruleThresholds = { ...RULE_DEFAULTS };

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

    this.hrMailNoEntryEnabled    = document.getElementById('hr-mail-no-entry-enabled');
    this.hrMailNoEntryDays       = document.getElementById('hr-mail-no-entry-days');
    this.hrMailOvertimeEnabled   = document.getElementById('hr-mail-overtime-enabled');
    this.hrMailOvertimeThreshold = document.getElementById('hr-mail-overtime-threshold');
    this.hrMailNegativeEnabled   = document.getElementById('hr-mail-negative-enabled');
    this.hrMailNegativeThreshold = document.getElementById('hr-mail-negative-threshold');
    this.hrNotificationsSection  = document.getElementById('hr-notifications-section');

    this.hrWarnNoEntryDays       = document.getElementById('hr-warn-no-entry-days');
    this.hrWarnOvertimeThreshold = document.getElementById('hr-warn-overtime-threshold');
    this.hrWarnNegativeThreshold = document.getElementById('hr-warn-negative-threshold');
    this.hrWarningThresholdsSection = document.getElementById('hr-warning-thresholds-section');

    return this;
  }

  // Simple selectors
  TS.$  = (sel, root = document) => root.querySelector(sel);
  TS.$$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  // Notification utility
  function notify(msg) {
    try {
      if (window.OC?.Notification?.showTemporary) {
        OC.Notification.showTemporary(msg);
      } else {
        console.log(msg);
      }
    } catch (error) {
      console.log(msg);
    }
  }

  function notifySessionExpired() {
    if (S.sessionExpiredNotified) return;
    S.sessionExpiredNotified = true;
    notify(t(S.appName, 'Session expired. Please reload the page.'));
  }

  function refreshRequestToken() {
    const token = getRequestToken();
    if (token) S.token = token;
    return token;
  }

  function ensureWriteAllowed() {
    const token = refreshRequestToken();
    if (!token) {
      notifySessionExpired();
      return false;
    }
    return true;
  }

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
  function dayLabel(dayIndex) {
    const key = DAY_KEYS[dayIndex] ?? '';
    return key ? t(S.appName, key) : '';
  }

  // "DD.MM.YYYY" format
  function formatDate(dateObj) {
    const day   = String(dateObj.getDate()).padStart(2, '0');
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const year  = dateObj.getFullYear();
    return `${day}.${month}.${year}`;
  }

  // "YYYY-MM-DD" format
  function toLocalIsoDate(dateObj) {
    const year  = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    const day   = String(dateObj.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  // Gets first and last date of month for given date
  function getMonthRange(date) {
    const y = date.getFullYear();
    const m = date.getMonth();
    return { from: new Date(y, m, 1), to: new Date(y, m + 1, 0) };
  }

  // Formats minutes to "HH:MM" format
  function minToHm(min) {
    if (min == null) return '--:--';
    const sign = min < 0 ? '-' : '';
    const absMin = Math.abs(min);
    const h = String(Math.floor(absMin / 60)).padStart(2, '0');
    const m = String(absMin % 60).padStart(2, '0');
    return `${sign}${h}:${m}`;
  }

  // Parses "HH:MM" format to minutes
  function hmToMin(str) {
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
  }

  function normalizeEndMin(startMin, endMin) {
    if (startMin == null || endMin == null) return endMin;
    return endMin < startMin ? endMin + 24 * 60 : endMin;
  }

  function calcWorkMinutes(startMin, endMin, breakMin = 0) {
    if (startMin == null || endMin == null) return null;
    const normEndMin = normalizeEndMin(startMin, endMin);
    const brk = (typeof breakMin === 'number' && Number.isFinite(breakMin)) ? breakMin : (Number(breakMin) || 0);
    return Math.max(0, normEndMin - startMin - brk);
  }

  // Picks daily minimum minutes from config
  function pickDailyMin(cfg) {
    if (!cfg) return null;
    if (Number.isFinite(cfg.dailyMin)) return cfg.dailyMin;
    if (Number.isFinite(cfg.workMinutes)) return cfg.workMinutes;
    if (typeof cfg.workMinutes === 'string') return hmToMin(cfg.workMinutes);
    return null;
  }

  // Checks timesheet entry against rules, returns issues as string
  function checkRules(entry, dateStr = null, holidayMap = {}, thresholds = null) {
    const tds = thresholds || S.ruleThresholds || RULE_DEFAULTS;
    const start = entry.startMin ?? hmToMin(entry.start);
    let end = entry.endMin ?? hmToMin(entry.end);
    if (start == null || end == null) return '';

    end = normalizeEndMin(start, end);
    const brk = entry.breakMinutes ?? 0;
    const gross = end - start;
    const dur = Math.max(0, gross - brk);

    const issues = [];

    if (dur > (tds.maxHours || RULE_DEFAULTS.maxHours) * 60) {
      issues.push(t(S.appName, 'Above maximum time'));
    }

    if (dur > (tds.breakLongHours || RULE_DEFAULTS.breakLongHours) * 60 && brk < (tds.breakLongMinutes || RULE_DEFAULTS.breakLongMinutes)) {
      issues.push(t(S.appName, 'Break too short'));
    } else if (dur > (tds.breakShortHours || RULE_DEFAULTS.breakShortHours) * 60 && brk < (tds.breakShortMinutes || RULE_DEFAULTS.breakShortMinutes)) {
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
  }

  // Shows visual feedback for saved row
  function showRowSavedFeedback(row) {
    row.classList.add('ts-row-saved');
    setTimeout(() => row.classList.remove('ts-row-saved'), 1200);
  }

  // Sets the configuration input fields
  function setConfigInputs(minutes, state) {
    const timeStr = minutes != null ? minToHm(minutes) : '';
    const dom = TS.dom;
    (dom.dailyMinInputs || []).forEach(input => { input.value = timeStr; });
    (dom.stateInputs || []).forEach(input => { input.value = state ?? ''; });
  }

  // Checks if there is any text selection
  function hasSelection() {
    const sel = window.getSelection?.();
    return !!sel && String(sel).trim() !== '';
  }

  // Checks if element is one of the timesheet cell input fields
  function isTimesheetCellField(el) {
    if (!el || !el.classList) return false;
    return el.classList.contains('startTime')
      || el.classList.contains('endTime')
      || el.classList.contains('breakMinutes')
      || el.classList.contains('commentInput');
  }

  // Parses break minutes input, returns minutes or null if invalid
  function parseBreakMinutesInput(raw) {
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
  }

  // "Month Year" format
  function monthLabel(dateObj) {
    return MONTH_FMT.format(dateObj);
  }

  // "YYYY-MM" format
  function toLocalMonthStr(dateObj) {
    const year  = dateObj.getFullYear();
    const month = String(dateObj.getMonth() + 1).padStart(2, '0');
    return `${year}-${month}`;
  }

  // Set to first day of month
  function firstOfMonth(dateObj) {
    return new Date(dateObj.getFullYear(), dateObj.getMonth(), 1);
  }

  // Add months, set to first day of month
  function addMonths(dateObj, delta) {
    return new Date(dateObj.getFullYear(), dateObj.getMonth() + delta, 1);
  }

  // API utility
  async function api(path, options = {}) {
    const method = String(options.method || 'GET').toUpperCase();
    const isWrite = !['GET', 'HEAD', 'OPTIONS'].includes(method);
    const token = isWrite ? (ensureWriteAllowed() ? S.token : null) : refreshRequestToken();
    if (isWrite && !token) {
      throw new Error('Session expired');
    }

    const url = OC.generateUrl(`/apps/${S.appName}${path}`);
    const res = await fetch(url, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        ...(token ? { 'requesttoken': token } : {}),
        ...(options.headers || {})
      }
    });
    
    if (res.status === 401 || res.status === 403) {
      notifySessionExpired();
    }

    if (!res.ok) {
      const errText = await res.text().catch(() => res.statusText);
      throw new Error(errText || `HTTP ${res.status}`);
    }
    return res.json().catch(() => null);
  }

  TS.notify = notify;
  U.dayLabel = dayLabel;
  U.formatDate = formatDate;
  U.toLocalIsoDate = toLocalIsoDate;
  U.getMonthRange = getMonthRange;
  U.minToHm = minToHm;
  U.hmToMin = hmToMin;
  U.normalizeEndMin = normalizeEndMin;
  U.calcWorkMinutes = calcWorkMinutes;
  U.pickDailyMin = pickDailyMin;
  U.checkRules = checkRules;
  U.showRowSavedFeedback = showRowSavedFeedback;
  U.setConfigInputs = setConfigInputs;
  U.hasSelection = hasSelection;
  U.isTimesheetCellField = isTimesheetCellField;
  U.parseBreakMinutesInput = parseBreakMinutesInput;
  U.monthLabel = monthLabel;
  U.toLocalMonthStr = toLocalMonthStr;
  U.firstOfMonth = firstOfMonth;
  U.addMonths = addMonths;
  U.ensureWriteAllowed = ensureWriteAllowed;
  TS.api = api;
  U.RULE_DEFAULTS = RULE_DEFAULTS;
})();
