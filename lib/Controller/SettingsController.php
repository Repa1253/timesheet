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
}