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
        <span id="month-display" class="month-display">Monat Jahr</span>
        <button type="button" class="month-nav" id="month-next">></button>
  
        <div class="ts-stats">
          <div><strong><?php p($l->t('Worked (month):')) ?></strong> <span id="worked-hours-month">--:--</span></div>
          <div><strong><?php p($l->t('Overtime (month):')) ?></strong> <span id="overtime-month">--:--</span></div>
          <div><strong><?php p($l->t('Total overtime:')) ?></strong> <span id="overtime-total">--:--</span></div>

          <button type="button" id="export-mine-csv" class="primary"><?php p($l->t('Export Month')) ?></button>
        </div>
      </div>
  
      <!-- Konfigurationszeile -->
      <div class="hr-config-row">            
        <label for="config-daily-min-mine"><?php p($l->t('Daily working time:')) ?></label>
        <input type="time" id="config-daily-min-mine" class="config-daily-min" value="08:00" />

        <label for="config-state-mine"><?php p($l->t('State:')) ?></label>
        <select id="config-state-mine" class="config-state">
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
          <th><?php p($l->t('Break (min)')) ?></th>
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
  </div>

  <!-- HR-Übersicht -->
  <?php if ($_['isHR']): ?>
    <div id="tab-hr" class="ts-tabview">

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

      <!-- Zielbereich für die ausgewählten Einträge eines Nutzers -->
      <div id="hr-user-entries" class="ts-hr-section" style="display: none;">

        <!-- Kopfzeile: Zurück + Titel nebeneinander -->
        <div class="hr-user-header-bar">
          <button id="hr-back-button" class="hr-back-button"><?php p($l->t('Back')) ?></button>
          <h4 id="hr-user-title"><?php p($l->t('Entries for:')) ?> <span></span></h4>
          <button type="button" id="export-hr-csv" class="primary"><?php p($l->t('Export Month')) ?></button>
        </div>

        <!-- Monat, Statistik und Konfiguration nebeneinander -->
        <div class="hr-user-controls-row">
          <div class="ts-month-row">
            <button type="button" class="month-nav" id="hr-month-prev"><</button>
            <span id="hr-month-display" class="month-display">Monat Jahr</span>
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
              <th><?php p($l->t('Break (min)')) ?></th>
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
      </div>
    </div>
  <?php endif; ?>
</section>