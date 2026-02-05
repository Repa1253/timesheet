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
    ['name' => 'overview#getHrUserListData',  'url' => '/api/hr/userlist',      'verb' => 'GET'],
    ['name' => 'overview#getOvertimeSummary', 'url' => '/api/overtime/summary', 'verb' => 'GET'],

    // REST: User config 
    ['name' => 'config#getUserConfig', 'url' => '/api/hr/config/{userId}', 'verb' => 'GET'],
    ['name' => 'config#setUserConfig', 'url' => '/api/hr/config/{userId}', 'verb' => 'PUT'],

    ['name' => 'config#getHrNotificationSettings', 'url' => '/api/hr/notifications', 'verb' => 'GET'],
    ['name' => 'config#setHrNotificationSettings', 'url' => '/api/hr/notifications', 'verb' => 'PUT'],
    ['name' => 'config#getHrWarningThresholds', 'url' => '/api/hr/warnings', 'verb' => 'GET'],
    ['name' => 'config#setHrWarningThresholds', 'url' => '/api/hr/warnings', 'verb' => 'PUT'],
    ['name' => 'config#getEffectiveRulesSelf', 'url' => '/api/rules/effective', 'verb' => 'GET'],
    ['name' => 'config#getEffectiveRulesForUser', 'url' => '/api/rules/effective/{userId}', 'verb' => 'GET'],

    // REST: Holidays 
    ['name' => 'holiday#getHolidays', 'url' => '/api/holidays', 'verb' => 'GET'],

    // Admin settings
    ['name' => 'settings#saveHrAccessRules',    'url' => '/settings/hr_access_rules',   'verb' => 'POST'],
    ['name' => 'settings#saveSpecialDaysCheck', 'url' => '/settings/specialdays_check', 'verb' => 'POST'],
    ['name' => 'settings#loadSpecialDaysCheck', 'url' => '/settings/specialdays_check', 'verb' => 'GET'],
  ],
];
