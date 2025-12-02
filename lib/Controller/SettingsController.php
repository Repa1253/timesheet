<?php

namespace OCA\Timesheet\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IRequest;

class SettingsController extends Controller {

  public function __construct(
    string $appName,
    IRequest $request,
    private IAppConfig $appConfig,
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
}