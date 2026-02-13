<section class="card">
  <!-- Tabs -->
  <div class="ts-tabs">
    <?php if ($_['isHR']): ?>
      <button class="ts-tab active" data-tab="mine"><?php p($l->t('Timesheet')) ?></button>
      <button class="ts-tab" data-tab="hr"><?php p($l->t('HR overview')) ?></button>
    <?php endif; ?>
  </div>

  <!-- Arbeitszeiterfassung -->
  <div id="tab-mine" class="ts-tabview active">
    <div class="ts-month-row-main">
      <div class="ts-month-row">
        <h3><?php p($l->t('My entries')) ?></h3>
  
        <button type="button" class="month-nav" id="month-prev"><</button>
        <button type="button" id="month-display" class="month-display month-display-btn">Month Year</button>
        <button type="button" class="month-nav" id="month-next">></button>
  
        <button type="button" id="export-mine-xlsx" class="primary"><?php p($l->t('Export')) ?></button>
        
        <div class="ts-stats">
          <div><strong><?php p($l->t('Worked (month):')) ?></strong> <span id="worked-hours-month">--:--</span></div>
          <div><strong><?php p($l->t('Overtime (month):')) ?></strong> <span id="overtime-month">--:--</span></div>
          <div><strong><?php p($l->t('Total overtime:')) ?></strong> <span id="overtime-total">--:--</span></div>
        </div>
      </div>
  
      <!-- Konfigurationszeile -->
      <div class="hr-config-row">            
        <label for="config-daily-min-mine"><?php p($l->t('Daily working time:')) ?></label>
        <input type="time" id="config-daily-min-mine" class="config-daily-min" value="08:00" />

        <label for="config-state-mine"><?php p($l->t('State:')) ?></label>
        <select id="config-state-mine" class="config-state">
          <option value=null></option>
          <option value="BW">Baden-Württemberg</option>
          <option value="BY" selected>Bayern</option>
          <option value="BE">Berlin</option>
          <option value="BB">Brandenburg</option>
          <option value="HB">Bremen</option>
          <option value="HH">Hamburg</option>
          <option value="HE">Hessen</option>
          <option value="MV">Mecklenburg-Vorpommern</option>
          <option value="NI">Niedersachsen</option>
          <option value="NW">Nordrhein-Westfalen</option>
          <option value="RP">Rheinland-Pfalz</option>
          <option value="SL">Saarland</option>
          <option value="SN">Sachsen</option>
          <option value="ST">Sachsen-Anhalt</option>
          <option value="SH">Schleswig-Holstein</option>
          <option value="TH">Thüringen</option>
        </select>
        
        <button id="save-config-btn-mine" class="save-config-btn"><?php p($l->t('Save')) ?></button>
      </div>
    </div>

    <table id="ts-table">
      <thead>
        <tr>
          <th colspan="2"><?php p($l->t('Date')) ?></th>
          <th><?php p($l->t('Status')) ?></th>
          <th><?php p($l->t('Start')) ?></th>
          <th><?php p($l->t('Break')) ?></th>
          <th><?php p($l->t('End')) ?></th>
          <th><?php p($l->t('Duration')) ?></th>
          <th><?php p($l->t('Difference')) ?></th>
          <th><?php p($l->t('Comment')) ?></th>
          <th><?php p($l->t('Warning')) ?></th>
        </tr>
      </thead>
      
      <tbody id="ts-body">
        <!-- wird per JS gefüllt -->
      </tbody>
    </table>

    <div class="ts-break-toggle" id="ts-break-toggle" role="group" aria-label="<?php p($l->t('Break input format')) ?>">
      <span class="ts-break-toggle-label"><?php p($l->t('Break')) ?>:</span>
      <span class="ts-break-mode-option is-active" data-break-mode-label="minutes"><?php p($l->t('Minutes')) ?></span>
      <label class="ts-break-switch">
        <input
          type="checkbox"
          class="ts-break-toggle-switch"
          role="switch"
          data-break-mode-off="minutes"
          data-break-mode-on="hours"
          aria-label="<?php p($l->t('Break input format')) ?>"
          aria-checked="false"
        >
        <span class="ts-break-switch-slider" aria-hidden="true"></span>
      </label>
      <span class="ts-break-mode-option" data-break-mode-label="hours"><?php p($l->t('Hours')) ?></span>
    </div>
  </div>

  <!-- HR-Übersicht -->
  <?php if ($_['isHR']): ?>
    <div id="tab-hr" class="ts-tabview">
      <div class="ts-hr-row">
        <div id="hr-infobox-section" class="ts-hr-section ts-infobox">
          <p class="ts-infobox-text">
            <?php p($l->t('If you would like to request changes to break durations, permitted daily hours or timesheets of other employees, please contact your Nextcloud admin.')) ?>
          </p>
        </div>

        <!-- Mitarbeitende -->
        <div id="hr-userlist-section" class="ts-hr-section">
          <h4><?php p($l->t('Employees')) ?></h4>
          <table class="grid hr-userlist">
            <thead>
              <tr>
                <th><?php p($l->t('Name')) ?></th>
                <th><?php p($l->t('Daily target')) ?></th>
                <th><?php p($l->t('Balance')) ?></th>
                <th><?php p($l->t('Last entry')) ?></th>
                <th><?php p($l->t('Days since last entry')) ?></th>
                <th><?php p($l->t('Warnings')) ?></th>
              </tr>
            </thead>
            <tbody id="hr-userlist">
              <!-- wird per JS gefüllt -->
            </tbody>
          </table>
        </div>

        <!-- HR-Statistiken -->
        <div id="hr-stats-section" class="ts-hr-section">
          <h4><?php p($l->t('Statistics')) ?></h4>
          <table class="grid hr-stats-table">
            <tbody>
              <tr>
                <th><?php p($l->t('Total hours of employees (this Month)')); ?></th>
                <td id="hr-stat-total-hours">-</td>
              </tr>
              <tr>
                <th><?php p($l->t('Employees with overtime')); ?></th>
                <td id="hr-stat-employees-overtime">-</td>
              </tr>
              <tr>
                <th><?php p($l->t('Employees with negative overtime')); ?></th>
                <td id="hr-stat-employees-negative">-</td>
              </tr>
              <tr>
                <th><?php p($l->t('Total overtime')); ?></th>
                <td id="hr-stat-total-overtime">-</td>
              </tr>
              <tr>
                <th><?php p($l->t('Total negative overtime')); ?></th>
                <td id="hr-stat-total-negative">-</td>
              </tr>
              <tr>
                <th><?php p($l->t('Sum of +/- overtimes')); ?></th>
                <td id="hr-stat-sum-overtimes">-</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div id="hr-notifications-section" class="ts-hr-section">
          <h4><?php p($l->t('Email notifications')) ?></h4>

          <table class="grid hr-notifications-table">
            <thead>
              <tr>
                <th></th>
                <th><?php p($l->t('Notification')) ?></th>
                <th><?php p($l->t('Threshold')) ?></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><input type="checkbox" id="hr-mail-no-entry-enabled"></td>
                <td><?php p($l->t('No entry reminder')) ?></td>
                <td>
                  <input type="number" id="hr-mail-no-entry-days" min="1" max="365" step="1" value="14">
                  <span><?php p($l->t('days')) ?></span>
                </td>
              </tr>

              <tr>
                <td><input type="checkbox" id="hr-mail-overtime-enabled"></td>
                <td><?php p($l->t('Overtime warning')) ?></td>
                <td>
                  <input type="time" id="hr-mail-overtime-threshold" value="10:00" step="60">
                  <span><?php p($l->t('hours')) ?></span>
                </td>
              </tr>

              <tr>
                <td><input type="checkbox" id="hr-mail-negative-enabled"></td>
                <td><?php p($l->t('Negative overtime warning')) ?></td>
                <td>
                  <input type="time" id="hr-mail-negative-threshold" value="10:00" step="60">
                  <span><?php p($l->t('hours')) ?></span>
                </td>
              </tr>
              <tr class="hr-hint-row">
                <td colspan="3"><?php p($l->t('When enabled, receive emails about warnings in the user list.')) ?></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div id="hr-warning-thresholds-section" class="ts-hr-section">
          <h4><?php p($l->t('Warnings')) ?></h4>

          <table class="grid hr-warning-thresholds-table">
            <thead>
              <tr>
                <th><?php p($l->t('Warning')) ?></th>
                <th><?php p($l->t('Threshold')) ?></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><?php p($l->t('No entry reminder')) ?></td>
                <td>
                  <input type="number" id="hr-warn-no-entry-days" min="1" max="365" step="1" value="14">
                  <span><?php p($l->t('days')) ?></span>
                </td>
              </tr>

              <tr>
                <td><?php p($l->t('Overtime warning')) ?></td>
                <td>
                  <input type="time" id="hr-warn-overtime-threshold" value="10:00" step="60">
                  <span><?php p($l->t('hours')) ?></span>
                </td>
              </tr>

              <tr>
                <td><?php p($l->t('Negative overtime warning')) ?></td>
                <td>
                  <input type="time" id="hr-warn-negative-threshold" value="10:00" step="60">
                  <span><?php p($l->t('hours')) ?></span>
                </td>
              </tr>
              <tr class="hr-hint-row">
                <td colspan="2"><?php p($l->t('Warnings are shown when employees reach the threshold.')) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Zielbereich für die ausgewählten Einträge eines Nutzers -->
      <div id="hr-user-entries" class="ts-hr-section" style="display: none;">

        <!-- Kopfzeile: Zurück + Titel nebeneinander -->
        <div class="hr-user-header-bar">
          <button id="hr-back-button" class="hr-back-button"><?php p($l->t('Back')) ?></button>
          <h4 id="hr-user-title"><?php p($l->t('Entries for:')) ?> <span></span></h4>
          <button type="button" id="export-hr-xlsx" class="primary"><?php p($l->t('Export')) ?></button>
        </div>

        <!-- Monat, Statistik und Konfiguration nebeneinander -->
        <div class="hr-user-controls-row">
          <div class="ts-month-row">
            <button type="button" class="month-nav" id="hr-month-prev"><</button>
            <button type="button" id="hr-month-display" class="month-display month-display-btn">Month Year</button>
            <button type="button" class="month-nav" id="hr-month-next">></button>
            
            <div class="ts-stats">
              <div><strong><?php p($l->t('Worked (month):')) ?></strong> <span id="worked-hours-month">--:--</span></div>
              <div><strong><?php p($l->t('Overtime (month):')) ?></strong> <span id="overtime-month">--:--</span></div>
              <div><strong><?php p($l->t('Total overtime:')) ?></strong> <span id="overtime-total">--:--</span></div>
            </div>
          </div>

          <!-- Konfigurationszeile -->
          <div class="hr-config-row">            
            <label for="config-daily-min-hr"><?php p($l->t('Daily working time:')) ?></label>
            <input type="time" id="config-daily-min-hr" class="config-daily-min" value="08:00" />

            <label for="config-state-hr"><?php p($l->t('State:')) ?></label>
            <select id="config-state-hr" class="config-state">
              <option value=null></option>
              <option value="BW">Baden-Württemberg</option>
              <option value="BY" selected>Bayern</option>
              <option value="BE">Berlin</option>
              <option value="BB">Brandenburg</option>
              <option value="HB">Bremen</option>
              <option value="HH">Hamburg</option>
              <option value="HE">Hessen</option>
              <option value="MV">Mecklenburg-Vorpommern</option>
              <option value="NI">Niedersachsen</option>
              <option value="NW">Nordrhein-Westfalen</option>
              <option value="RP">Rheinland-Pfalz</option>
              <option value="SL">Saarland</option>
              <option value="SN">Sachsen</option>
              <option value="ST">Sachsen-Anhalt</option>
              <option value="SH">Schleswig-Holstein</option>
              <option value="TH">Thüringen</option>
            </select>
            
            <button id="save-config-btn-hr" class="save-config-btn"><?php p($l->t('Save')) ?></button>
          </div>
        </div>
        
        <table id="hr-user-table" class="grid hr-table">
          <thead>
            <tr>
              <th colspan="2"><?php p($l->t('Date')) ?></th>
              <th><?php p($l->t('Status')) ?></th>
              <th><?php p($l->t('Start')) ?></th>
              <th><?php p($l->t('Break')) ?></th>
              <th><?php p($l->t('End')) ?></th>
              <th><?php p($l->t('Duration')) ?></th>
              <th><?php p($l->t('Difference')) ?></th>
              <th><?php p($l->t('Comment')) ?></th>
              <th><?php p($l->t('Warning')) ?></th>
            </tr>
          </thead>
          <tbody id="hr-user-body">
            <!-- wird per JS gefüllt -->
          </tbody>
        </table>

        <div class="ts-break-toggle" id="ts-break-toggle-hr" role="group" aria-label="<?php p($l->t('Break input format')) ?>">
          <span class="ts-break-toggle-label"><?php p($l->t('Break')) ?>:</span>
          <span class="ts-break-mode-option is-active" data-break-mode-label="minutes"><?php p($l->t('Minutes')) ?></span>
          <label class="ts-break-switch">
            <input
              type="checkbox"
              class="ts-break-toggle-switch"
              role="switch"
              data-break-mode-off="minutes"
              data-break-mode-on="hours"
              aria-label="<?php p($l->t('Break input format')) ?>"
              aria-checked="false"
            >
            <span class="ts-break-switch-slider" aria-hidden="true"></span>
          </label>
          <span class="ts-break-mode-option" data-break-mode-label="hours"><?php p($l->t('Hours')) ?></span>
        </div>
      </div>
    </div>
  <?php endif; ?>
</section>
