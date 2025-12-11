<?php

namespace OCA\Timesheet\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\Util;

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
		// nicht nötig
	}

	public function boot(IBootContext $context): void {
		// Wird bei jedem Request aufgerufen (App ist aktiviert).
	}
}
