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
  ) {
  }

  public function getForm(): TemplateResponse {
    $allGroupsObj = $this->groupManager->search('');
    $allGroups = array_map(fn($group) => $group->getGID(), $allGroupsObj);

    $hrGroups = json_decode($this->appConfig->getAppValueString('hr_groups'), true);
    $hrUserGroups = json_decode($this->appConfig->getAppValueString('hr_user_groups'), true);

    Util::addScript($this->appName, 'admin');
    
    return new TemplateResponse($this->appName, 'settings-admin', [
        'allGroups' => $allGroups,
        'hrGroups' => $hrGroups,
        'hrUserGroups' => $hrUserGroups,
    ]);
  }

  public function getSection(): string {
    return 'timesheet';
  }

  public function getPriority(): int {
    return 50;
  }
}