<?php

namespace OCA\Timesheet\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Settings\ISettings;
use OCP\Util;
use OCP\IL10N;

class AdminSettings implements ISettings {

  public function __construct(
    private IL10N $l,
    private IAppConfig $appConfig,
  ) {
  }

  public function getForm(): TemplateResponse {
    $hrGroups = $this->appConfig->getAppValueString('hr_groups');
    $hrUserGroup = $this->appConfig->getAppValueString('hr_user_group');

    Util::addScript('timesheet', 'admin');
    
    return new TemplateResponse(
      'timesheet',
      'settings-admin',
      [
        'hrGroups' => $hrGroups,
        'hrUserGroup' => $hrUserGroup,
      ]
    );
  }

  public function getSection(): string {
    return 'timesheet';
  }

  public function getPriority(): int {
    return 50;
  }
}