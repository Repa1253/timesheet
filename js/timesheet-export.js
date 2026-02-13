(function () {
  "use strict";

  const TS = window.Timesheet;
  if (!TS) return;

  const S = TS.state;
  const U = TS.util;

  TS.export = TS.export || {};
  const EX = TS.export;

  const exportChoices = {
    mine: { visible: false, ui: null },
    hr: { visible: false, ui: null },
  };

  function monthBounds(date) {
    const y = date.getFullYear();
    const m = date.getMonth();
    return { from: new Date(y, m, 1), to: new Date(y, m, 1) };
  }

  function yearBounds(date) {
    const y = date.getFullYear();
    return { from: new Date(y, 0, 1), to: new Date(y, 11, 1) };
  }

  function getTargetUser(kind) {
    if (kind !== 'hr') return null;
    return TS.dom.hrUserTitle?.querySelector('span')?.textContent?.trim() || null;
  }

  function hideExportChoice(kind) {
    const st = exportChoices[kind];
    if (!st) return;
    st.visible = false;
    if (st.ui?.container) st.ui.container.classList.remove('is-visible');
  }

  function runExport(kind, scope) {
    const base = (kind === 'hr') ? S.hrCurrentMonth : S.currentMonth;
    const bounds = (scope === 'year') ? yearBounds(base) : monthBounds(base);
    const fromStr = U.toLocalMonthStr(bounds.from);
    const toStr = U.toLocalMonthStr(bounds.to);

    let url = `/apps/${S.appName}/api/entries/export-xlsx?from=${encodeURIComponent(fromStr)}&to=${encodeURIComponent(toStr)}`;
    const targetUser = getTargetUser(kind);
    if (targetUser) url += `&user=${encodeURIComponent(targetUser)}`;
    if (U.USER_LOCALE) url += `&locale=${encodeURIComponent(U.USER_LOCALE)}`;

    hideExportChoice(kind);
    window.location.href = OC.generateUrl(url);
  }

  function ensureExportChoiceUi(kind) {
    const exportBtnId = (kind === 'hr') ? 'export-hr-xlsx' : 'export-mine-xlsx';
    const exportBtn = document.getElementById(exportBtnId);
    if (!exportBtn) return null;

    const uiId = `${exportBtnId}-choice-ui`;
    let container = document.getElementById(uiId);
    if (container) {
      exportChoices[kind].ui = exportChoices[kind].ui || {
        container,
        month: container.querySelector('[data-action="month"]'),
        year: container.querySelector('[data-action="year"]'),
      };
      return exportChoices[kind].ui;
    }

    container = document.createElement('span');
    container.id = uiId;
    container.className = 'ts-export-choice-ui';

    const makeBtn = (label, action) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = label;
      b.dataset.action = action;
      b.className = 'primary';
      return b;
    };

    const month = makeBtn(t(S.appName, 'Month export'), 'month');
    const year = makeBtn(t(S.appName, 'Year export'), 'year');

    container.appendChild(month);
    container.appendChild(year);
    exportBtn.insertAdjacentElement('afterend', container);

    const ui = { container, month, year };
    exportChoices[kind].ui = ui;

    month.addEventListener('click', () => runExport(kind, 'month'));
    year.addEventListener('click', () => runExport(kind, 'year'));

    return ui;
  }

  function handleExportClick(kind) {
    TS.dom.refresh();

    const st = exportChoices[kind];
    const ui = st.ui || ensureExportChoiceUi(kind);
    if (!ui) return;

    st.visible = !st.visible;
    ui.container.classList.toggle('is-visible', st.visible);
  }

  EX.handleExportClick = handleExportClick;
})();
