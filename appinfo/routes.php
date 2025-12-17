<?php
return [
  'routes' => [
    // UI
    ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

    // REST: Time entries
    ['name' => 'entry#index',      'url' => '/api/entries',             'verb' => 'GET'],
    ['name' => 'entry#create',     'url' => '/api/entries',             'verb' => 'POST'],
    ['name' => 'entry#update',     'url' => '/api/entries/{id}',        'verb' => 'PUT'],
    ['name' => 'entry#delete',     'url' => '/api/entries/{id}',        'verb' => 'DELETE'],
    ['name' => 'entry#exportXlsx', 'url' => '/api/entries/export-xlsx', 'verb' => 'GET'],

    // REST: Overview 
    ['name' => 'overview#users',              'url' => '/api/hr/users',         'verb' => 'GET'],
    ['name' => 'overview#getOvertimeSummary', 'url' => '/api/overtime/summary', 'verb' => 'GET'],

    // REST: User config 
    ['name' => 'config#getUserConfig', 'url' => '/api/hr/config/{userId}', 'verb' => 'GET'],
    ['name' => 'config#setUserConfig', 'url' => '/api/hr/config/{userId}', 'verb' => 'PUT'],

    // REST: Holidays 
    ['name' => 'holiday#getHolidays', 'url' => '/api/holidays', 'verb' => 'GET'],

    // Admin settings
    ['name' => 'settings#saveAdmin',          'url' => '/admin/settings',           'verb' => 'POST'],
    ['name' => 'settings#updateHrGroups',     'url' => '/settings/hr_groups',       'verb' => 'POST'],
    ['name' => 'settings#updateHrUserGroups', 'url' => '/settings/hr_user_groups',  'verb' => 'POST'],
    ['name' => 'settings#saveHrAccessRules',  'url' => '/settings/hr_access_rules', 'verb' => 'POST'],
  ],
];