<?php

namespace OCA\Timesheet\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCA\Timesheet\Db\EntryMapper;
use OCA\Timesheet\Db\UserConfigMapper;
use OCA\Timesheet\Service\HrService;
use OCA\Timesheet\Service\HolidayService;

class OverviewController extends Controller {

  public function __construct(
    string $appName,
    IRequest $request,
    private IAppConfig $appConfig,
    private EntryMapper $entryMapper,
    private UserConfigMapper $userConfigMapper,
    private IUserSession $userSession,
    private HrService $hrService,
    private HolidayService $holidayService,
  ) {
    parent::__construct($appName, $request);
  }

  #[NoAdminRequired]
  public function users(): DataResponse {
    if (!$this->hrService->isHr()) {
      return new DataResponse([], 403);
    }

    return new DataResponse($this->hrService->getAccessibleUsers());
  }

  #[NoAdminRequired]
  #[NoCSRFRequired]
  public function getOvertimeSummary(): JSONResponse {
    // Get current user
    $currentUser = $this->userSession->getUser();
    if (!$currentUser) {
      return new JSONResponse(['error' => 'Unauthorized'], 401);
    }

    // Get requested userId or fallback to current user
    $userId = (string)($this->request->getParam('user') ?? $currentUser->getUID());
    if ($userId === '') {
      $userId = $currentUser->getUID();
    }

    // Check access rights
    if ($userId !== $currentUser->getUID() && !$this->hrService->canAccessUser($userId)) {
      return new JSONResponse(['error' => 'Forbidden'], 403);
    }

    $dailyMin = 480;
    $state = null;
    try {
      $cfg = $this->userConfigMapper->findByUser($userId);
      $dailyMin = $cfg?->getWorkMinutes() ?? 480;
      $state = $cfg?->getState() ?? null;
    } catch (DoesNotExistException $e) {
      // Use defaults
    }

    $excludeSpecialDays = $this->appConfig->getAppValueString('specialdays_check', '0') === '1';
    $agg = $this->entryMapper->calculateOvertimeAggregate($userId, $excludeSpecialDays);

    if (!$agg) {
      $today = (new \DateTimeImmutable())->format('Y-m-d');
      return new JSONResponse([
        'from' => $today,
        'to' => $today,
        'totalMinutes' => 0,
        'totalWorkdays' => 0,
        'dailyMin' => $dailyMin,
        'overtimeMinutes' => 0,
      ]);
    }

    $effectiveWorkdays = (int)$agg['totalWorkdays'];

    if ($excludeSpecialDays && !empty($state)) {
      $from = (string)$agg['from'];
      $to   = (string)$agg['to'];

      $fromYear = (int)substr($from, 0, 4);
      $toYear   = (int)substr($to, 0, 4);

      $holidayDates = [];

      try {
        for ($year = $fromYear; $year <= $toYear; $year++) {
          $yearHolidays = $this->holidayService->getHolidays($year, $state);
          foreach ($yearHolidays as $date => $_name) {
            if ($date >= $from && $date <= $to) {
              $holidayDates[$date] = true;
            }
          }
        }
      } catch (\Throwable $e) {
        $holidayDates = [];
      }

      if ($holidayDates) {
        $holidayWorkdays = $this->entryMapper->countWorkdaysOnDates($userId, array_keys($holidayDates));
        $effectiveWorkdays = max(0, $effectiveWorkdays - $holidayWorkdays);
      }
    }

    $overtime = $agg['totalMinutes'] - ($effectiveWorkdays * $dailyMin);

    return new JSONResponse([
      'from' => $agg['from'],
      'to'   => $agg['to'],
      'totalMinutes' => $agg['totalMinutes'],
      'totalWorkdays' => $effectiveWorkdays,
      'dailyMin' => $dailyMin,
      'overtimeMinutes' => $overtime,
    ]);
  }
}