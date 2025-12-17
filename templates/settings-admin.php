<?php
  $allGroups     = $_['allGroups'] ?? [];
  $hrAccessRules = $_['hrAccessRules'] ?? [];
?>

<div id="timesheet-admin-settings" class="section">
  <h2><?php p($l->t('Timesheet settings')); ?></h2>

  <p class="ts-hint">
    <?php p($l->t('Define access rules: HR groups may access timesheets of the employee groups.')); ?>
  </p>

  <div id="timesheet-hr-rules"></div>

  <button type="button" class="button" id="timesheet-add-hr-rule">
    <?php p($l->t('Add new rule')); ?>
  </button>

  <script id="timesheet-all-groups-data" type="application/json">
    <?php print_unescaped(json_encode(array_values($allGroups), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>
  </script>

  <script id="timesheet-hr-rules-data" type="application/json">
    <?php print_unescaped(json_encode(array_values($hrAccessRules), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>
  </script>

  <style>
    #timesheet-hr-rules { margin: 12px 0 10px; display: grid; gap: 12px; }
    .ts-hint { margin: 8px 0 12px; opacity: .85; max-width: 900px; }
    .ts-rule { border: 1px solid var(--color-border); border-radius: 10px; padding: 12px; background: var(--color-main-background); }
    .ts-rule-head { display:flex; align-items:center; justify-content:space-between; gap: 8px; margin-bottom: 8px; }
    .ts-rule-title { font-weight: 700; }
    .ts-rule-delete { text-decoration:none; font-weight: 800; opacity:.7; }
    .ts-rule-delete:hover { opacity: 1; }
    .ts-rule-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 980px) { .ts-rule-grid { grid-template-columns: 1fr; } }
    .ts-rule-col label { font-weight: 600; display:block; margin-bottom: 4px; }
    .ts-chips { display:flex; flex-wrap:wrap; gap: 6px; min-height: 28px; margin: 4px 0 6px; }
    .ts-chip { background: var(--color-background-dark); border: 1px solid var(--color-border); border-radius: 999px; padding: 2px 10px; display:inline-flex; align-items:center; gap: 8px; }
    .ts-chip-remove { text-decoration:none; font-weight: 800; opacity: .75; }
    .ts-chip-remove:hover { opacity: 1; }
    .ts-rule-empty { opacity: .7; font-style: italic; }
    .ts-rule-select { max-width: 420px; }
  </style>
</div>