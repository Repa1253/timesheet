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

class PageController extends Controller {

	private const HR_GROUPS = ['xi-HR', 'xi-Master', 'sk-Master', 'op-Master', 'op-HR', 'sk-HR'];

	public function __construct(
		string $appName,
		IRequest $request,
		private IUserSession $userSession,
		private IGroupManager $groupManager
	) {
		parent::__construct($appName, $request);
	}

	private function isHr(): bool {
    $user = $this->userSession->getUser();
    if (!$user) return false;
    
    $uid = $user->getUID();
    foreach (self::HR_GROUPS as $group) {
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
