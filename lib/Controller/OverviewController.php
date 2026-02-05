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

  #[NoAdminRequired]
  #[NoCSRFRequired]
  public function getHrUserListData(): DataResponse {
    if (!$this->hrService->isHr()) {
      return new DataResponse([], 403);
    }

    $users = $this->hrService->getAccessibleUsers();
    if ($users === []) {
      return new DataResponse([]);
    }

    $userIds = array_map(fn($u) => (string)($u['id'] ?? ''), $users);
    $userIds = array_values(array_filter($userIds, fn($id) => $id !== ''));
    if ($userIds === []) {
      return new DataResponse([]);
    }

    $configs = $this->userConfigMapper->getConfigDataForUsers($userIds);

    $excludeSpecialDays = $this->appConfig->getAppValueString('specialdays_check', '0') === '1';
    $aggregates = $this->entryMapper->calculateOvertimeAggregatesForUsers($userIds, $excludeSpecialDays);

    $today = new \DateTimeImmutable('today');
    $fromDate = $today->modify('-6 months')->format('Y-m-d');
    $toDate = $today->format('Y-m-d');
    $lastEntryDates = $this->entryMapper->getLastEntryDatesForUsers($userIds, $fromDate, $toDate);

    $holidaysByYear = [];
    $out = [];

    foreach ($users as $user) {
      $uid = (string)($user['id'] ?? '');
      if ($uid === '') continue;

      $cfg = $configs[$uid] ?? ['dailyMin' => null, 'state' => null];
      $dailyMin = isset($cfg['dailyMin']) ? (int)$cfg['dailyMin'] : 480;
      if ($dailyMin <= 0) $dailyMin = 480;
      $state = isset($cfg['state']) ? (string)$cfg['state'] : '';

      $agg = $aggregates[$uid] ?? null;
      $totalMinutes = $agg['totalMinutes'] ?? 0;
      $effectiveWorkdays = $agg['totalWorkdays'] ?? 0;

      if ($agg && $excludeSpecialDays && $state !== '') {
        $from = (string)($agg['from'] ?? '');
        $to   = (string)($agg['to'] ?? '');

        if ($from !== '' && $to !== '') {
          $fromYear = (int)substr($from, 0, 4);
          $toYear   = (int)substr($to, 0, 4);

          $holidayDates = [];
          try {
            for ($year = $fromYear; $year <= $toYear; $year++) {
              $cacheKey = $state . ':' . $year;
              if (!array_key_exists($cacheKey, $holidaysByYear)) {
                $holidaysByYear[$cacheKey] = $this->holidayService->getHolidays($year, $state);
              }
              $yearHolidays = $holidaysByYear[$cacheKey];
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
            $holidayWorkdays = $this->entryMapper->countWorkdaysOnDates($uid, array_keys($holidayDates));
            $effectiveWorkdays = max(0, $effectiveWorkdays - $holidayWorkdays);
          }
        }
      }

      $overtimeMinutes = (int)$totalMinutes - ($effectiveWorkdays * $dailyMin);

      $out[] = [
        'id' => $uid,
        'name' => $user['name'] ?? $uid,
        'dailyMin' => $dailyMin,
        'overtimeMinutes' => $overtimeMinutes,
        'totalMinutes' => $totalMinutes,
        'lastEntryDate' => $lastEntryDates[$uid] ?? null,
      ];
    }

    return new DataResponse($out);
  }
}
