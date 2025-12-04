<?php

namespace OCA\Timesheet\Controller;

use OCA\Timesheet\Db\EntryMapper;
use OCA\Timesheet\Service\EntryService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IRequest;
use OCP\IUserSession;
use OCA\Timesheet\Service\HrService;

class EntryController extends Controller {

  public function __construct(
    string $appName,
    IRequest $request,
    private EntryMapper $mapper,
    private EntryService $service,
    private IUserSession $userSession,
    private HrService $hrService,
  ) {
    parent::__construct($appName, $request);
  }

  #[NoAdminRequired]
  public function index(?string $from = null, ?string $to = null, ?string $user = null): DataResponse {
    $from ??= date('Y-m-01');
    $to ??= date('Y-m-t');

    $currentUser = $this->userSession->getUser()->getUID();

    if ($user !== null && $this->hrService->isHr()) {
      $rows = $this->mapper->findByUserAndRange($user, $from, $to);
      return new DataResponse($rows);
    }

    $rows = $this->mapper->findByUserAndRange($currentUser, $from, $to);
    return new DataResponse($rows);
  }

  #[NoAdminRequired]
  public function create(string $workDate, string $start, string $end, int $breakMinutes = 0, ?string $comment = null): DataResponse {
    $current = $this->userSession->getUser();
    if (!$current) {
      return new DataResponse(['error' => 'Unauthorized'], 401);
    }
    $currentUid = $current->getUID();
    $targetUid = $currentUid;
    $userParam = $this->request->getParam('user');
    if ($userParam !== null && $this->hrService->isHr($currentUid)) {
      $targetUid = $userParam;
    }

    $payload = [
      'workDate' => $workDate,
      'startMin' => self::hmToMin($start),
      'endMin' => self::hmToMin($end),
      'breakMinutes' => $breakMinutes,
      'comment' => $comment,
    ];

    $entry = $this->service->create($payload, $targetUid);
    return new DataResponse($entry);
  }

  #[NoAdminRequired]
  public function update(int $id, ?string $workDate = null, ?string $start = null, ?string $end = null, ?int $breakMinutes = null, ?string $comment = null): DataResponse {
    $data = [];
    if ($workDate !== null) $data['workDate'] = $workDate;
    if ($start !== null)    $data['startMin'] = self::hmToMin($start);
    if ($end !== null)      $data['endMin']   = self::hmToMin($end);
    if ($breakMinutes !== null) $data['breakMinutes'] = $breakMinutes;
    if ($comment !== null)  $data['comment']  = $comment;
    $entry = $this->service->update($id, $data, $this->hrService->isHr());
    return new DataResponse($entry);
  }

  #[NoAdminRequired]
  public function delete(int $id): DataResponse {
    $this->service->delete($id, $this->hrService->isHr());
    return new DataResponse(['ok' => true]);
  }

  private static function hmToMin(string $hm): int {
    [$h, $m] = array_map('intval', explode(':', $hm));
    return max(0, $h*60 + $m);
  }
}