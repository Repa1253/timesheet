<?php

namespace OCA\Timesheet\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IRequest;
use OCP\IGroupManager;
use OCP\IUserSession;

class SettingsController extends Controller {

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

    $this->appConfig->setAppValueString('hr_access_rules', json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $allHr = [];
    $allUsers = [];
    foreach ($clean as $r) {
      $allHr = array_merge($allHr, $r['hrGroups']);
      $allUsers = array_merge($allUsers, $r['userGroups']);
    }
    $allHr = array_values(array_unique($allHr));
    $allUsers = array_values(array_unique($allUsers));
    $this->appConfig->setAppValueString('hr_groups', json_encode($allHr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $this->appConfig->setAppValueString('hr_user_groups', json_encode($allUsers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

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

      $out[] = [
        'id' => $id,
        'hrGroups' => $hrGroups,
        'userGroups' => $userGroups,
      ];
    }
    return $out;
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
}