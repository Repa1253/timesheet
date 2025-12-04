<?php

namespace OCA\Timesheet\Service;

use OCP\AppFramework\Services\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;

class HrService {

  /** @var string[] */
  private array $hrGroups = [];

  public function __construct(
    private IAppConfig $appConfig,
    private IUserSession $userSession,
    private IGroupManager $groupManager
  ) {
    $rawHr = $this->appConfig->getAppValueString('hr_groups');
    $hr = json_decode($rawHr, true);
    if (!is_array($hr)) {
      $hr = array_filter(array_map('trim', explode(',', (string)$rawHr)));
    }
    $this->hrGroups = $hr;
  }

  public function isHr(?string $uid = null): bool {
    if ($uid === null) {
      $user = $this->userSession->getUser();
      if (!$user) return false;
      $uid = $user->getUID();
    }

    foreach ($this->hrGroups as $group) {
      if ($group === '') {
        continue;
      }
      if ($this->groupManager->isInGroup($uid, $group)) {
        return true;
      }
    }
    return false;
  }

  public function getHrGroups(): array {
    return $this->hrGroups;
  }
}