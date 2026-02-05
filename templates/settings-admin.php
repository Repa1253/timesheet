<?php
  $allGroups     = $_['allGroups'] ?? [];
  $hrAccessRules = $_['hrAccessRules'] ?? [];
  $countSpecialDaysAsOvertime = (bool)($_['countSpecialDaysAsOvertime'] ?? false);
?>

<div id="timesheet-admin-settings" class="section">
  <h2><?php p($l->t('Timesheet settings')); ?></h2>

  <p class="ts-hint">
    <?php p($l->t('Define access rules: HR groups may access timesheets of the employee groups. Set time rules for those employee groups in each rule. Use priorities for when users are in multiple groups.')); ?>
  </p>

  <?php if (empty($allGroups)) : ?>
    <div class="ts-empty"><?php p($l->t('No groups found. Create at least one group to configure rules.')); ?></div>
  <?php endif; ?>

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

  <hr class="ts-divider" />

  <h2><?php p($l->t('Overtime calculation')); ?></h2>
  <p class="ts-hint">
    <?php p($l->t('Control how weekends and public holidays affect the expected working time.')); ?>
  </p>

  <div class="ts-setting">
    <input type="checkbox" class="checkbox" id="timesheet-specialdays-overtime" data-setting="countSpecialDaysAsOvertime" 
      <?php if ($countSpecialDaysAsOvertime) { print_unescaped('checked="checked"'); } ?>
    />
    <label for="timesheet-specialdays-overtime">
      <?php p($l->t('Count weekends and public holidays as overtime only')); ?>
    </label>
    <div class="ts-setting-desc">
      <?php p($l->t('If enabled, weekends and public holidays will not count as regular workdays. Any hours worked on these days are treated as overtime and do not create minus hours.')); ?>
    </div>
  </div>

  <style>
    #timesheet-hr-rules { margin: 12px 0 10px; display: grid; gap: 12px; }
    .ts-hint { margin: 8px 0 12px; opacity: .85; max-width: 900px; }
    .ts-rule { border: 1px solid var(--color-border); border-radius: 10px; padding: 12px; background: var(--color-main-background); }
    .ts-rule-head { display:flex; align-items:center; justify-content:space-between; gap: 8px; margin-bottom: 8px; }
    .ts-rule-title { font-weight: 700; }
    .ts-rule-head-actions { display:flex; align-items:center; gap: 10px; }
    .ts-rule-priority { display:flex; align-items:center; gap: 6px; font-weight: 600; }
    .ts-rule-priority input { width: 90px; }
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

    .ts-divider { margin: 18px 0 14px; border: 0; border-top: 1px solid var(--color-border); }
    .ts-setting { display: flex; align-items: flex-start; gap: 10px; padding: 10px 12px; border: 1px solid var(--color-border); }
    .ts-setting-desc { margin-top: 4px; opacity: .85; font-size: 13px; line-height: 1.4;}
    .ts-empty { padding: 10px; border: 1px dashed var(--color-border); border-radius: 8px; }
    .ts-inline { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
    .ts-rule-section { margin-top: 10px; border-top: 1px dashed var(--color-border); padding-top: 10px; }
    .ts-rule-section-title { font-weight: 700; margin-bottom: 6px; }
    .ts-rule-grid-compact { grid-template-columns: 1fr 1fr; }
    @media (max-width: 980px) { .ts-rule-grid-compact { grid-template-columns: 1fr; } }
  </style>
</div>
