<?php

namespace OCA\Timesheet\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Util;
use OCP\IConfig;

class PageController extends Controller {

	/** @var string[] */
	private array $hrGroups;

	public function __construct(
		string $appName,
		IRequest $request,
		IConfig $config,
		private IUserSession $userSession,
		private IGroupManager $groupManager
	) {
		parent::__construct($appName, $request);

		$raw = $config->getAppValue('timesheet', 'hr_groups', 'HR');
		$this->hrGroups = array_filter(array_map('trim', explode(',', $raw)));
	}

	private function isHr(): bool {
    $user = $this->userSession->getUser();
    if (!$user) return false;
    
    $uid = $user->getUID();
    foreach ($this->hrGroups as $group) {
      if ($this->groupManager->isInGroup($uid, $group)) return true;
    }

    return false;
  }

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		Util::addScript('timesheet', 'timesheet-main');
		Util::addStyle('timesheet', 'style');

		return new TemplateResponse($this->appName, 'main', [
			'isHR' => $this->isHr()
		]);
	}
}
