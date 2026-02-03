<?php

namespace OCA\Timesheet\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Settings\ISettings;
use OCP\Util;
use OCP\IGroupManager;

class AdminSettings implements ISettings {

  private const DEFAULT_RULE_SETTINGS = [
    'breakShortMinutes' => 30,
    'breakShortHours' => 6,
    'breakLongMinutes' => 45,
    'breakLongHours' => 9,
    'maxHours' => 10,
  ];

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

    $rules = $this->loadRules();
    
    return new TemplateResponse($this->appName, 'settings-admin', [
        'allGroups'     => $allGroups,
        'hrAccessRules' => $rules,
    ]);
  }

  private function loadRules(): array {
    $raw = (string)$this->appConfig->getAppValueString('hr_access_rules');
    if (trim($raw) === '') return [];
    $rules = json_decode($raw, true);
    if (!is_array($rules)) return [];
    return $this->sanitizeRules($rules);
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

      $out[] = array_merge([
        'id' => $id,
        'hrGroups' => $hrGroups,
        'userGroups' => $userGroups,
      ], $this->sanitizeRuleThresholds($r));
    }
    return $out;
  }

  /**
   * @param array $item
   * @return array<string, int|float>
   */
  private function sanitizeRuleThresholds(array $item): array {
    $d = self::DEFAULT_RULE_SETTINGS;

    return [
      'breakShortMinutes'      => $this->clampInt($item['breakShortMinutes']      ?? $d['breakShortMinutes'], 0, 600),
      'breakShortHours'        => $this->clampFloat($item['breakShortHours']      ?? $d['breakShortHours'],   0, 24),
      'breakLongMinutes'       => $this->clampInt($item['breakLongMinutes']       ?? $d['breakLongMinutes'],  0, 600),
      'breakLongHours'         => $this->clampFloat($item['breakLongHours']       ?? $d['breakLongHours'],    0, 24),
      'maxHours'               => $this->clampFloat($item['maxHours']             ?? $d['maxHours'],          0, 24),
    ];
  }

  private function clampInt($value, int $min, int $max): int {
    if (!is_numeric($value)) return $min;
    $v = (int)$value;
    return max($min, min($max, $v));
  }

  private function clampFloat($value, float $min, float $max): float {
    if (!is_numeric($value)) return $min;
    $v = (float)$value;
    if (is_nan($v) || is_infinite($v)) return $min;
    return max($min, min($max, round($v, 2)));
  }

  public function getSection(): string {
    return 'timesheet';
  }

  public function getPriority(): int {
    return 50;
  }
}
