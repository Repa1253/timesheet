<?php

namespace OCA\Timesheet\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;
use OCA\Timesheet\Service\HrService;

class PageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private HrService $hrService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		Util::addScript('core', 'l10n');
		Util::addTranslations('timesheet');

		Util::addScript('timesheet', 'timesheet-core');
		Util::addScript('timesheet', 'timesheet-entries');
		Util::addScript('timesheet', 'timesheet-hr');
		Util::addScript('timesheet', 'timesheet-export');
		Util::addScript('timesheet', 'timesheet-copy');
		Util::addScript('timesheet', 'timesheet-config');
		Util::addScript('timesheet', 'timesheet-main');

		Util::addStyle('timesheet', 'style');

		return new TemplateResponse($this->appName, 'main', [
			'isHR' => $this->hrService->isHr()
		]);
	}
}
