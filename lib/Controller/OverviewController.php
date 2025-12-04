<?php

namespace OCA\Timesheet\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use OCA\Timesheet\Db\EntryMapper;
use OCA\Timesheet\Db\UserConfigMapper;
use OCP\AppFramework\Services\IAppConfig;
use OCA\Timesheet\Service\HrService;

class OverviewController extends Controller {

  /** @var string[] */
  private array $hrUserGroups = [];

  public function __construct(
    string $appName,
    IRequest $request,
    IAppConfig $appConfig,
    private EntryMapper $entryMapper,
    private UserConfigMapper $userConfigMapper,
    private IGroupManager $groupManager,
    private IUserSession $userSession,
    private HrService $hrService,
  ) {
    parent::__construct($appName, $request);

    $rawUser = $appConfig->getAppValueString('hr_user_groups');
    $userGroups = json_decode($rawUser, true);
    if (!is_array($userGroups)) {
      $userGroups = array_filter(array_map('trim', explode(',', (string)$rawUser)));
    }
    $this->hrUserGroups = $userGroups;
  }

  #[NoAdminRequired]
  public function users(): DataResponse {
    if (!$this->hrService->isHr()) {
      return new DataResponse([], 403);
    }

    $result = [];
    foreach ($this->hrUserGroups as $groupName) {
      if ($groupName === '') continue;
      $group = $this->groupManager->get($groupName);
      if (!$group) continue;

      foreach ($group->getUsers() as $user) {
        $uid = $user->getUID();
        $result[$uid] = [
          'id' => $uid,
          'name' => $user->getDisplayName(),
        ];
      }
    }

    return new DataResponse(array_values($result));
  }

  #[NoAdminRequired]
  #[NoCSRFRequired]
  public function getOvertimeSummary(): JSONResponse {
    $currentUser = $this->userSession->getUser();
    if (!$currentUser) {
      return new JSONResponse(['error' => 'Unauthorized'], 401);
    }

    $userId = $this->request->getParam('user') ?? $currentUser->getUID();
    if ($userId !== $currentUser->getUID() && !$this->hrService->isHr()) {
      return new JSONResponse(['error' => 'Forbidden'], 403);
    }

    $agg = $this->entryMapper->calculateOvertimeAggregate($userId);

    $dailyMin = 0;
    try {
      $cfg = $this->userConfigMapper->findByUser($userId);
      $dailyMin = $cfg?->getWorkMinutes() ?? 0;
    } catch (DoesNotExistException $e) {
      $dailyMin = 480; // Fallback: 8 Stunden
    }

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

    $overtime = $agg['totalMinutes'] - ($agg['totalWorkdays'] * $dailyMin);

    return new JSONResponse([
      'from' => $agg['from'],
      'to'   => $agg['to'],
      'totalMinutes' => $agg['totalMinutes'],
      'totalWorkdays' => $agg['totalWorkdays'],
      'dailyMin' => $dailyMin,
      'overtimeMinutes' => $overtime,
    ]);
  }
}