(function () {
  "use strict";

  const TS = window.Timesheet;
  if (!TS) return;

  const S = TS.state;
  const U = TS.util;

  TS.export = TS.export || {};
  const EX = TS.export;

  // Export range states
  const exportRanges = {
    mine: { armed: false, from: null, to: null, ui: null, fixedLabelWidthPx: null },
    hr:   { armed: false, from: null, to: null, ui: null, fixedLabelWidthPx: null },
  };

  // Compute maximum width needed for range label
  function computeMaxRangeLabelWidthPx(labelEl) {
    const style = window.getComputedStyle(labelEl);

    const meas = document.createElement("span");
    meas.style.position = "absolute";
    meas.style.visibility = "hidden";
    meas.style.whiteSpace = "nowrap";
    meas.style.font = style.font;
    document.body.appendChild(meas);

    const year = new Date().getFullYear();
    const months = Array.from({ length: 12 }, (_, i) => new Date(year, i, 1));

    let max = 0;
    for (const m1 of months) {
      for (const m2 of months) {
        meas.textContent = `${U.monthLabel(m1)} - ${U.monthLabel(m2)}`;
        max = Math.max(max, meas.getBoundingClientRect().width);
      }
    }
    for (const m of months) {
      meas.textContent = U.monthLabel(m);
      max = Math.max(max, meas.getBoundingClientRect().width);
    }

    document.body.removeChild(meas);
    return Math.ceil(max) + 8;
  }

  // Apply fixed width to range label
  function applyFixedRangeLabelWidth(kind) {
    const st = exportRanges[kind];
    if (!st?.ui?.label) return;

    if (!st.fixedLabelwidthPx) {
      st.fixedLabelwidthPx = computeMaxRangeLabelWidthPx(st.ui.label);
    }

    st.ui.label.style.display = 'inline-block';
    st.ui.label.style.textAlign = 'center';
    st.ui.label.style.width = `${st.fixedLabelwidthPx}px`;
    st.ui.label.style.whiteSpace = 'nowrap';
  }

  // Ensure export range UI exists
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
    container.className = 'ts-export-range-ui';

    const makeBtn = (text, title, action) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = text;
      b.title = title;
      b.dataset.action = action;
      b.className = 'primary';

      return b;
    };

    const fromPrev = makeBtn('«', t(S.appName, 'Start month: previous'), 'from-prev');
    const fromNext = makeBtn('»', t(S.appName, 'Start month: next'), 'from-next');

    const label = document.createElement('span');
    label.className = 'ts-export-range-label';

    const toPrev = makeBtn('«', t(S.appName, 'End month: previous'), 'to-prev');
    const toNext = makeBtn('»', t(S.appName, 'End month: next'), 'to-next');

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

      if (which === 'from') st.from = U.addMonths(st.from, delta);
      if (which === 'to')   st.to   = U.addMonths(st.to, delta);

      if (st.from > st.to) {
        if (which === 'from') st.to = U.firstOfMonth(st.from);
        else st.from = U.firstOfMonth(st.to);
      }

      renderExportRange(kind);
    };

    fromPrev.addEventListener('click', () => shift('from', -1));
    fromNext.addEventListener('click', () => shift('from', +1));
    toPrev.addEventListener('click', () => shift('to', -1));
    toNext.addEventListener('click', () => shift('to', +1));

    return ui;
  }

  // Render export range UI
  function renderExportRange(kind) {
    const st = exportRanges[kind];
    const ui = st.ui || ensureExportRangeUi(kind);
    if (!ui || !st.from || !st.to) return;

    ui.container.classList.add('is-visible');

    const sameMonth = 
      st.from.getFullYear() === st.to.getFullYear() && 
      st.from.getMonth() === st.to.getMonth();

    ui.label.textContent = sameMonth 
      ? U.monthLabel(st.from) 
      : `${U.monthLabel(st.from)} – ${U.monthLabel(st.to)}`;
  }

  // Reset export range state
  function resetExportRange(kind) {
    const st = exportRanges[kind];
    st.armed = false;
    st.from = null;
    st.to = null;
    if (st.ui?.container) st.ui.container.classList.remove('is-visible');
  }

  // Handle export button click
  function handleExportClick(kind) {
    TS.dom.refresh();

    const st = exportRanges[kind];

    if (!st.armed) {
      const base = (kind === 'hr') ? S.hrCurrentMonth : S.currentMonth;
      st.from = U.firstOfMonth(base);
      st.to   = U.firstOfMonth(base);
      st.armed = true;
      renderExportRange(kind);
      return;
    }

    if (!st.from || !st.to) {
      resetExportRange(kind);
      return;
    }

    const fromStr = U.toLocalMonthStr(st.from);
    const toStr   = U.toLocalMonthStr(st.to);

    let url = `/apps/${S.appName}/api/entries/export-xlsx?from=${encodeURIComponent(fromStr)}&to=${encodeURIComponent(toStr)}`;

    if (kind === 'hr') {
      const uid = TS.dom.hrUserTitle?.querySelector('span')?.textContent?.trim();
      if (uid) url += `&user=${encodeURIComponent(uid)}`;
    }

    if (U.USER_LOCALE) url += `&locale=${encodeURIComponent(U.USER_LOCALE)}`;

    resetExportRange(kind);
    window.location.href = OC.generateUrl(url);
  }

  // Exported functions
  EX.ensureExportRangeUi = ensureExportRangeUi;
  EX.renderExportRange = renderExportRange;
  EX.resetExportRange = resetExportRange;
  EX.handleExportClick = handleExportClick;
})();