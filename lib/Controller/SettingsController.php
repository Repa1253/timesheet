<?php

namespace OCA\Timesheet\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IRequest;
use OCP\IGroupManager;
use OCP\IUserSession;

class SettingsController extends Controller {

  private const GROUP_DEFAULTS = [
    'priority' => 1,
    'breakShortMinutes' => 30,
    'breakShortHours' => 6,
    'breakLongMinutes' => 45,
    'breakLongHours' => 9,
    'maxHours' => 10,
  ];

  public function __construct(
    string $appName,
    IRequest $request,
    private IAppConfig $appConfig,
    private IGroupManager $groupManager,
    private IUserSession $userSession,
  ) {
    parent::__construct($appName, $request);
  }

  /**
   * @AdminRequired
   * @CSRFCheck
   */
  public function saveAdmin(string $hrGroups, string $hrUserGroup): DataResponse {
    $hrGroups = trim($hrGroups);
    $hrUserGroup = trim($hrUserGroup);
    
    $this->appConfig->setAppValueString('hr_groups', $hrGroups);
    $this->appConfig->setAppValueString('hr_user_group', $hrUserGroup);

    return new DataResponse(['status' => 'success'], Http::STATUS_OK);
  }

  public function updateHrGroups(): DataResponse {
    $group = $this->request->getParam('group');
    $remove = $this->request->getParam('remove') === '1';
    $json = $this->appConfig->getAppValueString('hr_groups');
    $groups = json_decode($json, true) ?: [];

    if ($remove) {
      $groups = array_values(array_filter($groups, fn($g) => $g !== $group));
    } else {
      if (!in_array($group, $groups, true)) {
        $groups[] = $group;
      }
    }
    $this->appConfig->setAppValueString('hr_groups', json_encode($groups));
    
    return new DataResponse(['hr_groups' => $groups]);
  }

  public function updateHrUserGroups(): DataResponse {
    $group = $this->request->getParam('group');
    $remove = $this->request->getParam('remove') === '1';
    $json = $this->appConfig->getAppValueString('hr_user_groups');
    $groups = json_decode($json, true) ?: [];

    if ($remove) {
      $groups = array_values(array_filter($groups, fn($g) => $g !== $group));
    } else {
      if (!in_array($group, $groups, true)) {
        $groups[] = $group;
      }
    }
    $this->appConfig->setAppValueString('hr_user_groups', json_encode($groups));

    return new DataResponse(['hr_user_groups' => $groups]);
  }

  public function saveHrAccessRules(): DataResponse {
    $user = $this->userSession->getUser();
    $uid = $user?->getUID();
    if (!$uid || !$this->groupManager->isAdmin($uid)) {
      return new DataResponse(['message' => 'Forbidden'], 403);
    }

    $raw = (string)$this->request->getParam('rules', '[]');
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
      return new DataResponse(['message' => 'Invalid rules'], 400);
    }

    $clean = $this->sanitizeRules($decoded);
    $dupes = $this->findDuplicateUserGroups($clean);
    if (!empty($dupes)) {
      return new DataResponse([
        'message' => 'Duplicate employee groups in rules',
        'groups' => array_values($dupes),
      ], 400);
    }

    $this->appConfig->setAppValueString('hr_access_rules', json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return new DataResponse(['rules' => $clean]);
  }

  private function sanitizeRules(array $rules): array {
    $out = [];
    foreach ($rules as $r) {
      if (!is_array($r)) continue;
      
      $id = isset($r['id']) ? trim((string)$r['id']) : '';
      if ($id === '') continue;

      $hrGroups = isset($r['hrGroups']) && is_array($r['hrGroups']) ? $r['hrGroups'] : [];
      $userGroups = isset($r['userGroups']) && is_array($r['userGroups']) ? $r['userGroups'] : [];

      $hrGroups = $this->cleanGroupList($hrGroups);
      $userGroups = $this->cleanGroupList($userGroups);

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
    $d = self::GROUP_DEFAULTS;

    return [
      'priority'               => $this->clampInt($item['priority']               ?? $d['priority'],          0, 9999),
      'breakShortMinutes'      => $this->clampInt($item['breakShortMinutes']      ?? $d['breakShortMinutes'], 0, 600),
      'breakShortHours'        => $this->clampFloat($item['breakShortHours']      ?? $d['breakShortHours'],   0, 24),
      'breakLongMinutes'       => $this->clampInt($item['breakLongMinutes']       ?? $d['breakLongMinutes'],  0, 600),
      'breakLongHours'         => $this->clampFloat($item['breakLongHours']       ?? $d['breakLongHours'],    0, 24),
      'maxHours'               => $this->clampFloat($item['maxHours']             ?? $d['maxHours'],          0, 24),
    ];
  }

  private function cleanGroupList(array $groups): array {
    $groups = array_values(array_filter(array_map(fn($g) => trim((string)$g), $groups), fn($v) => $v !== ''));
    $groups = array_values(array_unique($groups));

    $final = [];
    foreach ($groups as $g) {
      if ($this->groupManager->groupExists($g)) $final[] = $g;
    }
    return $final;
  }

  /**
   * @param array<int, array<string, mixed>> $rules
   * @return string[]
   */
  private function findDuplicateUserGroups(array $rules): array {
    $seen = [];
    $dupes = [];
    foreach ($rules as $rule) {
      $userGroups = isset($rule['userGroups']) && is_array($rule['userGroups']) ? $rule['userGroups'] : [];
      foreach ($userGroups as $g) {
        $key = (string)$g;
        if ($key === '') continue;
        if (isset($seen[$key])) {
          $dupes[$key] = $key;
        } else {
          $seen[$key] = true;
        }
      }
    }
    return array_values($dupes);
  }

  /**
   * @AdminRequired
   * @CSRFCheck
   */
  public function saveSpecialDaysCheck(): DataResponse {
    $user = $this->userSession->getUser();
    $uid = $user?->getUID();
    if (!$uid || !$this->groupManager->isAdmin($uid)) {
      return new DataResponse(['message' => 'Forbidden'], 403);
    }

    $raw = (string)$this->request->getParam('specialDaysCheck', 'false');
    $checked = filter_var($raw, FILTER_VALIDATE_BOOLEAN);

    $this->appConfig->setAppValueString('specialdays_check', $checked ? '1' : '0');

    return new DataResponse(['check' => $checked]);
  }

  #[NoAdminRequired]
  #[NoCSRFRequired]
  public function loadSpecialDaysCheck(): DataResponse {
    $user = $this->userSession->getUser();
    if (!$user) {
      return new DataResponse(['message' => 'Unauthorized'], 401);
    }

    $raw = $this->appConfig->getAppValueString('specialdays_check', '0');
    $checked = $raw === '1';

    return new DataResponse(['check' => $checked]);
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
