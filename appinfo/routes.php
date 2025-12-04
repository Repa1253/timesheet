<?php
return [
  'routes' => [
    // UI
    ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

    // REST: eigene Zeiten (MA) oder alle (HR)
    ['name' => 'entry#index',  'url' => '/api/entries',        'verb' => 'GET'],
    ['name' => 'entry#create', 'url' => '/api/entries',        'verb' => 'POST'],
    ['name' => 'entry#update', 'url' => '/api/entries/{id}',   'verb' => 'PUT'],
    ['name' => 'entry#delete', 'url' => '/api/entries/{id}',   'verb' => 'DELETE'],

    // REST: HR-Übersicht (Regelverletzungen/Anomalien)
    ['name' => 'overview#users',              'url' => '/api/hr/users',         'verb' => 'GET'],
    ['name' => 'overview#getOvertimeSummary', 'url' => '/api/overtime/summary', 'verb' => 'GET'],

    // REST: HR-Konfiguration
    ['name' => 'config#getUserConfig', 'url' => '/api/hr/config/{userId}', 'verb' => 'GET'],
    ['name' => 'config#setUserConfig', 'url' => '/api/hr/config/{userId}', 'verb' => 'PUT'],

    ['name' => 'holiday#getHolidays', 'url' => '/api/holidays', 'verb' => 'GET'],

    // Admin settings
    ['name' => 'settings#saveAdmin',          'url' => '/admin/settings',          'verb' => 'POST'],
    ['name' => 'settings#updateHrGroups',     'url' => '/settings/hr_groups',      'verb' => 'POST'],
    ['name' => 'settings#updateHrUserGroups', 'url' => '/settings/hr_user_groups', 'verb' => 'POST'],
  ],
];