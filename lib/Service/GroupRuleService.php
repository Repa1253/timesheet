<?php

namespace OCA\Timesheet\Service;

use OCP\AppFramework\Services\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;

class GroupRuleService {
  private const DEFAULTS = [
    'breakShortMinutes' => 30,
    'breakShortHours' => 6,
    'breakLongMinutes' => 45,
    'breakLongHours' => 9,
    'maxHours' => 10,
  ];
  private const PRIORITY_DEFAULT = 1;
  private const PRIORITY_MIN = 0;
  private const PRIORITY_MAX = 9999;

  public function __construct(
    private IAppConfig $appConfig,
    private IGroupManager $groupManager,
    private IUserManager $userManager,
    private IUserSession $userSession,
  ) {}

  /**
   * Returns effective rule thresholds for the given user.
   * If userId is null, resolves to the current logged-in user.
   *
   * @return array<string, int|float>
   */
  public function getEffectiveRules(?string $userId = null): array {
    $uid = $userId;
    if ($uid === null) {
      $current = $this->userSession->getUser();
      if (!$current) return self::DEFAULTS;
      $uid = $current->getUID();
    }

    $user = $this->userManager->get($uid);
    if (!$user) return self::DEFAULTS;

    if (method_exists($this->groupManager, 'getUserGroupIds')) {
      $userGroups = $this->groupManager->getUserGroupIds($user);
    } else {
      $userGroups = array_map(
        fn($g) => $g->getGID(),
        $this->groupManager->getUserGroups($user)
      );
    }
    if (!$userGroups) return self::DEFAULTS;

    $rules = $this->loadRules();
    if (!$rules) return self::DEFAULTS;

    $bestRule = null;
    $bestPriority = null;

    foreach ($rules as $rule) {
      $ruleGroups = $rule['userGroups'] ?? [];
      if (!$ruleGroups || !array_intersect($ruleGroups, $userGroups)) continue;

      $priority = $this->getRulePriority($rule);
      if ($bestRule === null || $priority < $bestPriority) {
        $bestRule = $rule;
        $bestPriority = $priority;
      }
    }

    if ($bestRule !== null) {
      return $this->extractThresholds($bestRule);
    }

    return self::DEFAULTS;
  }

  /**
   * @return array<int, array{id:string,hrGroups:string[],userGroups:string[],priority:int}>
   */
  private function loadRules(): array {
    $raw = (string)$this->appConfig->getAppValueString('hr_access_rules');
    if (trim($raw) === '') return [];
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return [];
    return $this->sanitizeRules($decoded);
  }

  /**
   * @param array $rules
   * @return array<int, array<string, mixed>>
   */
  private function sanitizeRules(array $rules): array {
    $cleaned = [];

    foreach ($rules as $rule) {
      if (!is_array($rule)) continue;

      $id = isset($rule['id']) ? trim((string)$rule['id']) : '';
      if ($id === '') continue;

      $hrGroups = $rule['hrGroups'] ?? [];
      $userGroups = $rule['userGroups'] ?? [];

      $hrGroups = is_array($hrGroups) ? $hrGroups : [];
      $userGroups = is_array($userGroups) ? $userGroups : [];

      $hrGroups = $this->cleanGroupList($hrGroups);
      $userGroups = $this->cleanGroupList($userGroups);

      $cleaned[] = array_merge([
        'id' => $id,
        'hrGroups' => $hrGroups,
        'userGroups' => $userGroups,
      ], $this->sanitizeRuleThresholds($rule));
    }

    return $cleaned;
  }

  /**
   * @param array $item
   * @return array<string, int|float>
   */
  private function sanitizeRuleThresholds(array $item): array {
    $d = self::DEFAULTS;

    return [
      'priority'               => $this->clampInt($item['priority']               ?? self::PRIORITY_DEFAULT, self::PRIORITY_MIN, self::PRIORITY_MAX),
      'breakShortMinutes'      => $this->clampInt($item['breakShortMinutes']      ?? $d['breakShortMinutes'], 0, 600),
      'breakShortHours'        => $this->clampFloat($item['breakShortHours']      ?? $d['breakShortHours'],   0, 24),
      'breakLongMinutes'       => $this->clampInt($item['breakLongMinutes']       ?? $d['breakLongMinutes'],  0, 600),
      'breakLongHours'         => $this->clampFloat($item['breakLongHours']       ?? $d['breakLongHours'],    0, 24),
      'maxHours'               => $this->clampFloat($item['maxHours']             ?? $d['maxHours'],          0, 24),
    ];
  }

  /**
   * @param array<string, int|float> $rule
   * @return array<string, int|float>
   */
  private function extractThresholds(array $rule): array {
    $out = [];
    foreach (self::DEFAULTS as $key => $fallback) {
      $out[$key] = array_key_exists($key, $rule) ? $rule[$key] : $fallback;
    }
    return $out;
  }

  /**
   * @param array<string, mixed> $rule
   */
  private function getRulePriority(array $rule): int {
    return $this->clampInt($rule['priority'] ?? self::PRIORITY_DEFAULT, self::PRIORITY_MIN, self::PRIORITY_MAX);
  }

  /**
   * @param array $groups
   * @return string[]
   */
  private function cleanGroupList(array $groups): array {
    $groups = array_values(array_filter(array_map(fn($g) => trim((string)$g), $groups), fn($g) => $g !== ''));
    $groups = array_values(array_unique($groups));

    $final = [];
    foreach ($groups as $g) {
      if ($this->groupManager->groupExists($g)) {
        $final[] = $g;
      }
    }
    return $final;
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
}
