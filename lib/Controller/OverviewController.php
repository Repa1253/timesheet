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

class OverviewController extends Controller {

  private const HR_GROUPS = ['xi-HR', 'xi-Master', 'sk-Master', 'op-Master', 'op-HR', 'sk-HR'];

  public function __construct(
    string $appName,
    IRequest $request,
    private EntryMapper $entryMapper,
    private UserConfigMapper $userConfigMapper,
    private IGroupManager $groupManager,
    private IUserSession $userSession
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
  public function users(): DataResponse {
    if (!$this->isHr()) {
      return new DataResponse([], 403);
    }

    $group = $this->groupManager->get('Zeitnachweis');
    if (!$group) {
      return new DataResponse([], 404);
    }

    $users = $group->getUsers();
    $result = array_values(array_map(fn($u) => [
      'id' => $u->getUID(),
      'name' => $u->getDisplayName(),
    ], $users));

    return new DataResponse($result);
  }

  #[NoAdminRequired]
  #[NoCSRFRequired]
  public function getOvertimeSummary(): JSONResponse {
    $currentUser = $this->userSession->getUser();
    if (!$currentUser) {
      return new JSONResponse(['error' => 'Unauthorized'], 401);
    }

    $userId = $this->request->getParam('user') ?? $currentUser->getUID();
    if ($userId !== $currentUser->getUID() && !$this->isHr()) {
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