(function () {
  "use strict";

  const TS = window.Timesheet;
  if (!TS) return;

  const S = TS.state;
  const U = TS.util;

  TS.copy = TS.copy || {};
  const CP = TS.copy;

  // Initialize copy functionality
  function init() {
    // Handle pointer over to track hovered row
    document.addEventListener('pointerover', (e) => {
      const row = e.target.closest(S.TS_ROW_SCOPE);
      if (row) S.tsHoveredRow = row;
    });

    // Handle pointer out to clear hovered row
    document.addEventListener('pointerout', (e) => {
      const row = e.target.closest(S.TS_ROW_SCOPE);
      if (row && S.tsHoveredRow === row) S.tsHoveredRow = null;
    });

    // Copy event handler
    document.addEventListener('copy', (e) => {
      const el = document.activeElement;
      const tag = (el?.tagName || '').toUpperCase();
      const isInputLike = tag === 'INPUT' || tag === 'TEXTAREA';

      if (isInputLike && U.isTimesheetCellField(el)) {
        const hasSelection =
          typeof el.selectionStart === 'number' &&
          typeof el.selectionEnd === 'number' &&
          el.selectionEnd > el.selectionStart

        if (hasSelection) return;
        if (!e.clipboardData) return;

        e.clipboardData.setData('text/plain', String(el.value ?? ''));
        e.preventDefault();
        TS.notify(t(S.appName, 'Copied to clipboard'));
        return;
      }

      const inEditable = isInputLike || !!el?.isContentEditable;
      if (inEditable) return;

      if (!S.tsHoveredRow) return;
      if (U.hasSelection()) return;
      if (!e.clipboardData) return;

      const start = (S.tsHoveredRow.querySelector('.startTime')?.value || '').trim();
      const brk   = (S.tsHoveredRow.querySelector('.breakMinutes')?.value || '').trim();
      const end   = (S.tsHoveredRow.querySelector('.endTime')?.value || '').trim();
      let comment = (S.tsHoveredRow.querySelector('.commentInput')?.value || '').trim();
      comment = comment.replace(/\t/g, ' ').replace(/\r?\n/g, ' '); // Remove tabs and new lines

      e.clipboardData.setData('text/plain', [start, brk, end, comment].join('\t'));
      e.preventDefault();
      TS.notify(t(S.appName, 'Row copied to clipboard'));
    });
  }

  // Expose init function
  CP.init = init;
})();