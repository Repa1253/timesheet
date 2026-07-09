<?php

namespace OCA\Timesheet\Controller;

use OCA\Timesheet\Service\HrService;
use OCA\Timesheet\Service\MonthlyBalanceService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\IRequest;
use OCP\IUserSession;

class BalanceController extends Controller {
  public function __construct(
    string $appName,
    IRequest $request,
    private IUserSession $userSession,
    private HrService $hrService,
    private MonthlyBalanceService $monthlyBalanceService,
  ) {
    parent::__construct($appName, $request);
  }

  #[NoAdminRequired]
  #[NoCSRFRequired]
  public function getMonth(string $userId, int $year, int $month): DataResponse {
    if (!$this->canAccess($userId)) {
      return new DataResponse(['message' => 'Forbidden'], 403);
    }

    $balance = $this->monthlyBalanceService->ensureMonthSnapshot($userId, $year, $month);
    return new DataResponse($balance);
  }

  private function canAccess(string $targetUid): bool {
    $current = $this->userSession->getUser();
    if (!$current) return false;
    $currentUid = $current->getUID();
    if ($currentUid === $targetUid) return true;
    return $this->hrService->canAccessUser($targetUid, $currentUid);
  }
}
