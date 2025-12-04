<?php

namespace OCA\Timesheet\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Annotations\NoAdminRequired;
use OCP\AppFramework\Annotations\NoCSRFRequired;
use OCP\AppFramework\Annotations\Route;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IDBConnection;
use OCA\Timesheet\Service\HrService;

class ConfigController extends Controller {

  private IUserSession $userSession;
  private IDBConnection $db;

  public function __construct(
    string $appName,
    IRequest $request,
    IUserSession $userSession,
    IDBConnection $db,
    private HrService $hrService,
  ) {
    parent::__construct($appName, $request);
    $this->userSession  = $userSession;
    $this->db           = $db;
  }

  /**
  * @NoAdminRequired
  * @NoCSRFRequired
  * @Route("/api/hr/config/{userId}", methods={"GET"})
  *
  * Returns the configuration for the given user (dailyMin and state).
  */
  public function getUserConfig(string $userId): DataResponse {
    $this->assertConfigAccess($userId);

    $qb = $this->db->getQueryBuilder();
    $qb->select('work_minutes', 'state')
      ->from('ts_user_config')
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
    $row = $qb->executeQuery()->fetch();
    if ($row === false) {
      // If no config exists for this user, return null values
      return new DataResponse(['dailyMin' => null, 'state' => null], Http::STATUS_OK);
    }

    $dailyMin = (int)$row['work_minutes'];
    $state = $row['state'];
    return new DataResponse(['dailyMin' => $dailyMin, 'state' => $state], Http::STATUS_OK);
  }

  /**
  * @NoAdminRequired
  * @NoCSRFRequired
  * @Route("/api/hr/config/{userId}", methods={"PUT"})
  *
  * Creates or updates the configuration for the given user.
  * Expects JSON body with fields "dailyMin" and "state".
  */
  public function setUserConfig(string $userId, int $dailyMin, string $state): DataResponse {
    $this->assertConfigAccess($userId);

    // Try updating existing config
    $qb = $this->db->getQueryBuilder();
    $qb->update('ts_user_config')
      ->set('work_minutes', $qb->createNamedParameter($dailyMin))
      ->set('state', $qb->createNamedParameter($state))
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
     $affectedRows = $qb->executeStatement();

     if ($affectedRows === 0) {
      // No existing row for this user: insert a new config
      $qbInsert = $this->db->getQueryBuilder();
      $qbInsert->insert('ts_user_config')
        ->values([
          'user_id'      => $qbInsert->createNamedParameter($userId),
          'work_minutes' => $qbInsert->createNamedParameter($dailyMin),
          'state'        => $qbInsert->createNamedParameter($state)
        ]);
      $qbInsert->executeStatement();
    }

    return new DataResponse(['dailyMin' => $dailyMin, 'state' => $state], Http::STATUS_OK);
  }

  /**
  * Helper to ensure the current user belongs to the HR group.
  * Throws ForbiddenException if access is not allowed.
  */
  private function assertConfigAccess(string $targetUid): void {
    $currentUser = $this->userSession->getUser();
    if (!$currentUser) {
      throw new \Exception("Not logged in", Http::STATUS_FORBIDDEN);
    }

    $currentUid = $currentUser->getUID();
    if ($currentUid === $targetUid) return;
    if ($this->hrService->isHr($currentUid)) return;
    
    throw new \Exception("Access denied", Http::STATUS_FORBIDDEN);
  }
}