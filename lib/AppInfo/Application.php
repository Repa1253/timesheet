<?php

namespace OCA\Timesheet\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\BackgroundJob\IJobList;
use OCA\Timesheet\BackgroundJob\HrNotificationJob;

class Application extends App implements IBootstrap {
	public const APP_ID = 'timesheet';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
		if (file_exists($vendorAutoload)) {
			require_once $vendorAutoload;
		}
	}

	public function register(IRegistrationContext $context): void {
		// not needed
	}

	public function boot(IBootContext $context): void {
		$server = $context->getServerContainer();

		/** @var IJobList $jobList */
		$jobList = $server->get(IJobList::class);
		$jobClass = HrNotificationJob::class;

		if (method_exists($jobList, 'has')) {
			if (!$jobList->has($jobClass, null)) {
				$jobList->add($jobClass, null);
			}
			return;
		}
	}
}
