<?php
  $allGroups  = $_['allGroups'] ?? [];
  $hrGroups    = $_['hrGroups'] ?? [];
  $hrUserGroups = $_['hrUserGroups'] ?? [];
?>

<div id="timesheet-admin-settings" class="section">
  <h2>Arbeitszeitnachweis - Einstellungen</h2>

  <div class="settings-field">
    <label for="timesheet_hr_groups_select">HR-Gruppen</label><br>
    <div id="timesheet_hr_groups_selected">
      <?php foreach ($hrGroups as $group): ?>
        <span class="chip">
          <?php echo htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?>
          <a href="#" class="remove-group" data-field="hr_groups" data-group="<?php echo htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?>">x</a>
        </span>
      <?php endforeach; ?>
    </div>
    <select id="timesheet_hr_groups_select">
      <option value="">Gruppe hinzufügen...</option>
      <?php foreach ($allGroups as $group): ?>
        <option value="<?php echo htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="settings-field" style="margin-top: 1em;">
    <label for="timesheet_hr_user_groups_select">Mitarbeitenden</label><br>
    <div id="timesheet_hr_user_group_selected">
      <?php foreach ($hrUserGroups as $group): ?>
        <span class="chip">
          <?php echo htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?>
          <a href="#" class="remove-group" data-field="hr_user_groups" data-group="<?php echo htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?>">x</a>
        </span>
      <?php endforeach; ?>
    </div>
    <select id="timesheet_hr_user_groups_select">
      <option value="">Gruppe hinzufügen...</option>
      <?php foreach ($allGroups as $group): ?>
        <option value="<?php echo htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <style>
    .chip {
      display: inline-block;
      border: 1px solid #ccc;
      border-radius: 6px;
      padding: 2px 6px;
      margin: 2px 4px 2px 0;
    }
    .chip .remove-group {
      text-decoration: none;
      margin-left: 6px;
      color: #555;
      font-weight: bold;
    }
    .chip .remove-group:hover {
      color: #000;
    }
  </style>
</div>