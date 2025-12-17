<?php

namespace OCA\Timesheet\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Settings\ISettings;
use OCP\Util;
use OCP\IGroupManager;

class AdminSettings implements ISettings {

  public function __construct(
    private IAppConfig $appConfig,
    private IGroupManager $groupManager,
    private string $appName,
  ) {}

  public function getForm(): TemplateResponse {
    Util::addScript($this->appName, 'admin');

    $allGroups = [];
    foreach ($this->groupManager->search('') as $g) {
      $allGroups[] = $g->getGID();
    }
    sort($allGroups, SORT_NATURAL | SORT_FLAG_CASE);

    $rules = $this->loadRulesWithLegacyFallback();
    
    return new TemplateResponse($this->appName, 'settings-admin', [
        'allGroups'     => $allGroups,
        'hrAccessRules' => $rules,
    ]);
  }

  private function loadRulesWithLegacyFallback(): array {
    $raw = (string)$this->appConfig->getAppValueString('hr_access_rules');
    if (trim($raw) === '') $raw = '[]';
    $rules = json_decode($raw, true);

    if (is_array($rules) && count($rules) > 0) {
      return $this->sanitizeRules($rules);
    }

    // Legacy format fallback
    $legacyHrGroups = json_decode((string)$this->appConfig->getAppValueString('hr_groups'), true);
    $legacyUserGroups = json_decode((string)$this->appConfig->getAppValueString('hr_user_groups'), true);
    $legacyHrGroups = is_array($legacyHrGroups) ? $legacyHrGroups : [];
    $legacyUserGroups = is_array($legacyUserGroups) ? $legacyUserGroups : [];

    if ($legacyHrGroups || $legacyUserGroups) {
      return [[
        'id' => 'legacy',
        'hrGroups' => $legacyHrGroups,
        'userGroups' => $legacyUserGroups,
      ]];
    }

    return [];
  }

  private function sanitizeRules(array $rules): array {
    $out = [];
    foreach ($rules as $r) {
      if (!is_array($r)) continue;
      
      $id = isset($r['id']) ? trim((string)$r['id']) : '';
      if ($id === '') continue;

      $hrGroups = isset($r['hrGroups']) && is_array($r['hrGroups']) ? $r['hrGroups'] : [];
      $userGroups = isset($r['userGroups']) && is_array($r['userGroups']) ? $r['userGroups'] : [];

      $hrGroups = array_values(array_unique(array_values(array_filter(array_map('trim', $hrGroups), fn($v) => $v !== ''))));
      $userGroups = array_values(array_unique(array_values(array_filter(array_map('trim', $userGroups), fn($v) => $v !== ''))));

      $out[] = [
        'id' => $id,
        'hrGroups' => $hrGroups,
        'userGroups' => $userGroups,
      ];
    }
    return $out;
  }

  public function getSection(): string {
    return 'timesheet';
  }

  public function getPriority(): int {
    return 50;
  }
}