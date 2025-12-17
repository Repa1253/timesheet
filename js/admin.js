(function() {
  'use strict';

  const root = document.getElementById('timesheet-admin-settings');
  if (!root) return;

  // Helper function to read JSON from a script tag
  function readJson(id, fallback) {
    const element = document.getElementById(id);
    if (!element) return fallback;
    try {
      return JSON.parse(element.textContent || "null") ?? fallback;
    } catch (e) {
      console.warn("Timesheet admin settings: invalid JSON in", id, e);
      return fallback;
    }
  }

  const allGroups = readJson('timesheet-all-groups-data', []);
  let rules = readJson('timesheet-hr-rules-data', []);

  const rulesEl = document.getElementById('timesheet-hr-rules');
  const addBtn = document.getElementById('timesheet-add-hr-rule');

  function notify(msg) {
    try {
      if (window.OC?.Notification?.showTemporary) OC.Notification.showTemporary(msg);
      else console.log(msg);
    } catch {
      console.log(msg);
    }
  }

  function newRuleId() {
    if (window.crypto?.randomUUID) return crypto.randomUUID();
    return 'r_' + Math.random().toString(16).slice(2) + "_" + Date.now();
  }

  function sanitizeRules(input) {
    const out = [];
    (Array.isArray(input) ? input : []).forEach((r) => {
      if (!r || typeof r !== 'object') return;
      const id = String(r.id || "").trim();
      if (!id) return;

      const hrGroups = Array.isArray(r.hrGroups) ? r.hrGroups : [];
      const userGroups = Array.isArray(r.userGroups) ? r.userGroups : [];

      const clean = (arr) => {
        const s = new Set();
        arr.forEach((v) => {
          const x = String(v || "").trim();
          if (x) s.add(x);
        });
        return Array.from(s);
      };

      out.push({ id, hrGroups: clean(hrGroups), userGroups: clean(userGroups) });
    });
    return out;
  }

  async function saveRules(nextRules) {
    const url = OC.generateUrl('/apps/timesheet/settings/hr_access_rules');
    const formData = new FormData();
    formData.append('rules', JSON.stringify(nextRules));

    const response = await fetch(url, {
      method: 'POST',
      headers: { 'requesttoken': OC.requestToken },
      body: formData,
    });

    if (!response.ok) {
      const errorText = await response.text().catch(() => '');
      throw new Error(errorText || `HTTP ${response.status}`);
    }

    const json = await response.json().catch(() => null);
    const serverRules = json?.rules;
    return sanitizeRules(serverRules ?? nextRules);
  }

  function optHtml(value) {
    const s = String(value);
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function render() {
    rules = sanitizeRules(rules);
    rulesEl.innerHTML = '';

    if (rules.length === 0) {
      const div = document.createElement('div');
      div.className = 'ts-rule-empty';
      div.textContent = t('timesheet', 'No rules yet. Add one to restrict HR access based on groups.');
      rulesEl.appendChild(div);
      return;
    }

    rules.forEach((rule, index) => {
      const card = document.createElement('div');
      card.className = 'ts-rule';
      card.dataset.ruleId = rule.id;

      const groupOptions = ['<option value="">' + optHtml(t('timesheet', 'Add group...')) + '</option>']
        .concat(allGroups.map((g) => `<option value="${optHtml(g)}">${optHtml(g)}</option>`))
        .join('');

      const chips = (arr, kind) => {
        if (!arr.length) return "";
        return arr
          .map((g) => {
            return `
              <span class="ts-chip">${optHtml(g)} 
                <a href="#" class="ts-chip-remove" data-group="${optHtml(g)}" data-kind="${optHtml(kind)}" title="${optHtml(t('timesheet', 'Remove'))}">×</a>
              </span>`;
          })
          .join('');
      };

      card.innerHTML = `
        <div class="ts-rule-head">
          <div class="ts-rule-title">${optHtml(t("timesheet", "Rule"))} ${index + 1}</div>
          <a href="#" class="ts-rule-delete" title="${optHtml(t("timesheet", "Delete rule"))}">×</a>
        </div>

        <div class="ts-rule-grid">
          <div class="ts-rule-col">
            <label>${optHtml(t("timesheet", "HR groups"))}</label>
            <div class="ts-chips" data-kind="hrGroups">${chips(rule.hrGroups, "hrGroups")}</div>
            <select class="ts-rule-select" data-kind="hrGroups">${groupOptions}</select>
          </div>

          <div class="ts-rule-col">
            <label>${optHtml(t("timesheet", "Employee groups"))}</label>
            <div class="ts-chips" data-kind="userGroups">${chips(rule.userGroups, "userGroups")}</div>
            <select class="ts-rule-select" data-kind="userGroups">${groupOptions}</select>
          </div>
        </div>
      `;

      rulesEl.appendChild(card);
    });
  }

  function mutateRule(ruleId, fn) {
    const next = sanitizeRules(rules).map((r) => (r.id === ruleId ? fn({ ...r }) : r));
    rules = next;
  }

  async function persistAndRender(nextRules) {
    try {
      rules = await saveRules(nextRules);
      render();
    } catch (error) {
      console.error('Failed to save HR access rules:', error);
      notify(t('timesheet', 'Saving failed'));
      render();
    }
  }

  addBtn?.addEventListener('click', async () => {
    const next = sanitizeRules(rules);
    next.push({ id: newRuleId(), hrGroups: [], userGroups: [] });
    await persistAndRender(next);
  });

  rulesEl.addEventListener('click', async (e) => {
    const aRemove = e.target.closest('.ts-chip-remove');
    const aDelete = e.target.closest('.ts-rule-delete');

    if (aRemove) {
      e.preventDefault();
      const card = e.target.closest('.ts-rule');
      const ruleId = card?.dataset.ruleId;
      const kind = aRemove.dataset.kind;
      const group = aRemove.dataset.group;
      if (!ruleId || !kind || !group) return;

      mutateRule(ruleId, (r) => {
        r[kind] = (Array.isArray(r[kind]) ? r[kind] : []).filter((x) => x !== group);
        return r;
      });

      await persistAndRender(rules);
      return;
    }

    if (aDelete) {
      e.preventDefault();
      const card = e.target.closest('.ts-rule');
      const ruleId = card?.dataset.ruleId;
      if (!ruleId) return;

      const next = sanitizeRules(rules).filter((r) => r.id !== ruleId);
      await persistAndRender(next); 
    }
  });

  rulesEl.addEventListener('change', async (e) => {
    const sel = e.target.closest('.ts-rule-select');
    if (!sel) return;

    const card = e.target.closest('.ts-rule');
    const ruleId = card?.dataset.ruleId;
    const kind = sel.dataset.kind;
    const group = sel.value;
    
    sel.value = '';
    if (!ruleId || !kind || !group) return;

    mutateRule(ruleId, (r) => {
      const arr = Array.isArray(r[kind]) ? r[kind] : [];
      if (!arr.includes(group)) arr.push(group);
      r[kind] = arr;
      return r;
    });

    await persistAndRender(rules);
  });

  render();
})();