<?php

namespace OCA\Timesheet\Service;

use OCP\AppFramework\Services\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;

class HrService {

  /** @var array<int, array{id:string,hrGroups:string[],userGroups:string[]}> */
  private array $rules = [];

  /** @var string[] */
  private array $hrGroups = [];

  public function __construct(
    private IAppConfig $appConfig,
    private IUserSession $userSession,
    private IGroupManager $groupManager
  ) {
    $this->rules = $this->loadRulesWithLegacyFallback();
    $this->hrGroups = $this->collectAllHrGroups($this->rules);
  }

  public function isHr(?string $uid = null): bool {
    if ($uid === null) {
      $user = $this->userSession->getUser();
      if (!$user) return false;
      $uid = $user->getUID();
    }

    foreach ($this->rules as $rule) {
      foreach ($rule['hrGroups'] as $group) {
        if ($group === '') continue;
        if ($this->groupManager->isInGroup($uid, $group)) {
          return true;
        }
      } 
    }

    return false;
  }

  /**
   * @return string[]
   */
  public function getAllowedUserGroups(?string $hrUid = null): array {
    if ($hrUid === null) {
      $user = $this->userSession->getUser();
      if (!$user) return [];
      $hrUid = $user->getUID();
    }

    $allowed = [];

    foreach ($this->rules as $rule) {
      $isInHrGroup = false;
      
      foreach ($rule['hrGroups'] as $g) {
        if ($g === '') continue;
        if ($this->groupManager->isInGroup($hrUid, $g)) {
          $isInHrGroup = true;
          break;
        }
      }
      
      if (!$isInHrGroup) continue;

      foreach ($rule['userGroups'] as $ug) {
        if ($ug === '') continue;
        $allowed[$ug] = true;
      }
    }

    return array_keys($allowed);
  }

  public function canAccessUser(string $targetUid, ?string $hrUid = null): bool {
    if ($hrUid === null) {
      $user = $this->userSession->getUser();
      if (!$user) return false;
      $hrUid = $user->getUID();
    }

    if ($targetUid === $hrUid) return true;
    if (!$this->isHr($hrUid)) return false;

    $allowedGroups = $this->getAllowedUserGroups($hrUid);
    foreach ($allowedGroups as $group) {
      if ($this->groupManager->isInGroup($targetUid, $group)) {
        return true;
      }
    }

    return false;
  }

  /**
   * @return array<int, array{id:string,name:string}>
   */
  public function getAccessibleUsers(?string $hrUid = null): array {
    if ($hrUid === null) {
      $user = $this->userSession->getUser();
      if (!$user) return [];
      $hrUid = $user->getUID();
    }

    if (!$this->isHr($hrUid)) return [];
    
    $allowedGroups = $this->getAllowedUserGroups($hrUid);
    if (!$allowedGroups) return [];

    $result = [];

    foreach ($allowedGroups as $group) {
      $grp = $this->groupManager->get($group);
      if (!$grp) continue;

      foreach ($grp->getUsers() as $user) {
        $uid = $user->getUID();
        $result[$uid] = [
          'id' => $uid,
          'name' => $user->getDisplayName(),
        ];
      }
    }

    $out = array_values($result);
    usort($out, fn($a, $b) => strnatcasecmp((string)$a['name'], (string)$b['name']));
    return $out;
  }

  /**
   * @return string[]
   */
  public function getHrGroups(): array {
    return $this->hrGroups;
  }

  /**
   * @return array<int, array{id:string,hrGroups:string[],userGroups:string[]}>
   */
  private function loadRulesWithLegacyFallback(): array {
    $rulesJson = (string)$this->appConfig->getAppValueString('hr_access_rules');
    $rules = json_decode($rulesJson, true);

    if (is_array($rules) && count($rules) > 0) {
      $clean = $this->sanitizeRules($rules);
      if ($clean) return $clean;
    }

    $rawHr = (string)$this->appConfig->getAppValueString('hr_groups');
    $hr = json_decode($rawHr, true);
    if (!is_array($hr)) {
      $hr = array_filter(array_map('trim', explode(',', $rawHr)));
    }

    $rawUsers = (string)$this->appConfig->getAppValueString('hr_user_groups');
    $users = json_decode($rawUsers, true);
    if (!is_array($users)) {
      $users = array_filter(array_map('trim', explode(',', $rawUsers)));
    }

    $hr = $this->cleanGroupList($hr);
    $users = $this->cleanGroupList($users);

    if (!$hr) return [];

    return [[
      'id' => 'legacy',
      'hrGroups' => $hr,
      'userGroups' => $users,
    ]];
  }

  /**
   * @param array $rules
   * @return array<int, array{id:string,hrGroups:string[],userGroups:string[]}>
   */
  private function sanitizeRules(array $rules): array {
    $cleaned = [];
   
    foreach ($rules as $rule) {
      if (!is_array($rule)) continue;
      
      $id = isset($rule['id']) ? trim((string)$rule['id']) : '';
      if ($id === '') continue;

      $hrGroups = $rule['hrGroups'] ?? $rule['hr_groups'] ?? [];
      $userGroups = $rule['userGroups'] ?? $rule['user_groups'] ?? [];

      $hrGroups = is_array($hrGroups) ? $hrGroups : [];
      $userGroups = is_array($userGroups) ? $userGroups : [];

      $hrGroups = $this->cleanGroupList($hrGroups);
      $userGroups = $this->cleanGroupList($userGroups);

      if (!$hrGroups) continue;

      $cleaned[] = [
        'id' => $id,
        'hrGroups' => $hrGroups,
        'userGroups' => $userGroups,
      ];
    }

    return $cleaned;
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

  /**
   * @param array<int, array{id:string,hrGroups:string[],userGroups:string[]}> $rules
   * @return string[]
   */
  private function collectAllHrGroups(array $rules): array {
    $all = [];
    foreach ($rules as $rule) {
      foreach (($rule['hrGroups'] ?? []) as $g) {
        $all[$g] = true;
      }
    }
    return array_keys($all);
  }
}