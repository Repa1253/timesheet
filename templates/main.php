<section class="card">
  <!-- Tabs -->
  <div class="ts-tabs">
    <?php if ($_['isHR']): ?>
      <button class="ts-tab active" data-tab="mine">Arbeitszeitnachweis</button>
      <button class="ts-tab" data-tab="hr">HR-Übersicht</button>
    <?php endif; ?>
  </div>

  <!-- Arbeitszeiterfassung -->
  <div id="tab-mine" class="ts-tabview active">
    <div class="ts-month-row-main">
      <div class="ts-month-row">
        <h3>Meine Einträge</h3>
  
        <button type="button" class="month-nav" id="month-prev"><</button>
        <span id="month-display" class="month-display">Monat Jahr</span>
        <button type="button" class="month-nav" id="month-next">></button>
  
        <div class="ts-stats">
          <div><strong>Gearbeitet (Monat):</strong> <span id="worked-hours-month">--:--</span></div>
          <div><strong>Überstunden (Monat):</strong> <span id="overtime-month">--:--</span></div>
          <div><strong>Gesamt-Überstunden:</strong> <span id="overtime-total">--:--</span></div>
        </div>
      </div>
  
      <!-- Konfigurationszeile -->
      <div class="hr-config-row">            
        <label for="config-daily-min-mine">Arbeitszeit:</label>
        <input type="time" id="config-daily-min-mine" class="config-daily-min" value="08:00" />

        <label for="config-state-mine">Bundesland:</label>
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
        
        <button id="save-config-btn-mine" class="save-config-btn">Speichern</button>
      </div>
    </div>

    <table id="ts-table">
      <thead>
        <tr>
          <th colspan="2">Datum</th>
          <th>Status</th>
          <th>Start</th>
          <th>Pause (Min)</th>
          <th>Ende</th>
          <th>Dauer</th>
          <th>Differenz</th>
          <th>Kommentar</th>
          <th>Warnung</th>
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
        <h4>Mitarbeitende</h4>
        <table class="grid hr-userlist">
          <thead>
            <tr>
              <th>Name</th>
              <th>Soll pro Tag</th>
              <th>Saldo</th>
              <th>Letzter Eintrag</th>
              <th>Tage seit letzen Eintrag</th>
              <th>Fehlermeldungen</th>
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
          <button id="hr-back-button" class="hr-back-button">Zurück</button>
          <h4 id="hr-user-title">Einträge von: <span></span></h4>
        </div>

        <!-- Monat, Statistik und Konfiguration nebeneinander -->
        <div class="hr-user-controls-row">
          <div class="ts-month-row">
            <button type="button" class="month-nav" id="hr-month-prev"><</button>
            <span id="hr-month-display" class="month-display">Monat Jahr</span>
            <button type="button" class="month-nav" id="hr-month-next">></button>
            
            <div class="ts-stats">
              <div><strong>Gearbeitet (Monat):</strong> <span id="worked-hours-month">--:--</span></div>
              <div><strong>Überstunden (Monat):</strong> <span id="overtime-month">--:--</span></div>
              <div><strong>Gesamt-Überstunden:</strong> <span id="overtime-total">--:--</span></div>
            </div>
          </div>

          <!-- Konfigurationszeile -->
          <div class="hr-config-row">            
            <label for="config-daily-min-hr">Arbeitszeit:</label>
            <input type="time" id="config-daily-min-hr" class="config-daily-min" value="08:00" />

            <label for="config-state-hr">Bundesland:</label>
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
            
            <button id="save-config-btn-hr" class="save-config-btn">Speichern</button>
          </div>
        </div>
        
        <table id="hr-user-table" class="grid hr-table">
          <thead>
            <tr>
              <th colspan="2">Datum</th>
              <th>Status</th>
              <th>Start</th>
              <th>Pause (Min)</th>
              <th>Ende</th>
              <th>Dauer</th>
              <th>Differenz</th>
              <th>Kommentar</th>
              <th>Warnung</th>
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