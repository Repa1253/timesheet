<?php
  $hrGroups = $_['hrGroups'] ?? '';
  $hrUserGroup = $_['hrUserGroup'] ?? '';
?>

<div id="timesheet-admin-settings" class="section">
  <h2>Arbeitszeitnachweis - Einstellungen</h2>

  <p>
    <label for="timesheet_hr_groups">
      HR-Gruppen (Komma-getrennt)
    </label><br>
    <input type="text"
            id="timesheet_hr_groups"
            class="timesheet-admin-input"
            value="<?php echo htmlspecialchars($hrGroups, ENT_QUOTES, 'UTF-8'); ?>"
    />
  </p>

  <p>
    <label for="timesheet_hr_user_group">
      Gruppe mit Mitarbeitenden
    </label><br>
    <input type="text" 
            id="timesheet_hr_user_group"
            class="timesheet-admin-input"
            value="<?php echo htmlspecialchars($hrUserGroup, ENT_QUOTES, 'UTF-8'); ?>"
    />
  </p>

  <p>
    <button class="primary" id="timesheet_admin_save">Speichern</button>
    <span id="timesheet-admin-status" class="msg"></span>
  </p>

  <style>
    #timesheet-admin-settings .timesheet-admin-input {
      width: 100%;
      max-width: 400px;
      box-sizing: border-box;
    }
  </style>
</div>