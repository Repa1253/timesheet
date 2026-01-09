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

  private const DEFAULT_WORK_MINUTES = 480;

  private const DEFAULT_NO_ENTRY_ENABLED = false;
  private const DEFAULT_NO_ENTRY_DAYS = 14;

  private const DEFAULT_OVERTIME_ENABLED = false;
  private const DEFAULT_OVERTIME_THRESHOLD = 600; // 10 hours

  private const DEFAULT_NEGATIVE_OVERTIME_ENABLED = false;
  private const DEFAULT_NEGATIVE_OVERTIME_THRESHOLD = 600; // 10 hours

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
   * @NoAdminRequired
   * @NoCSRFRequired
   * @Route("/api/hr/notifications", methods={"GET"})
   * 
   * Returns email notification settings for the logged-in HR user
   */
  public function getHrNotificationSettings(): DataResponse {
    $currentUser = $this->assertHrUser();

    $defaults = [
      'noEntryEnabled' => self::DEFAULT_NO_ENTRY_ENABLED,
      'noEntryDays' => self::DEFAULT_NO_ENTRY_DAYS,
      'overtimeEnabled' => self::DEFAULT_OVERTIME_ENABLED,
      'overtimeThresholdMinutes' => self::DEFAULT_OVERTIME_THRESHOLD,
      'negativeOvertimeEnabled' => self::DEFAULT_NEGATIVE_OVERTIME_ENABLED,
      'negativeOvertimeThresholdMinutes' => self::DEFAULT_NEGATIVE_OVERTIME_THRESHOLD,
    ];

    $qb = $this->db->getQueryBuilder();
    $qb->select(
        'mail_no_entry_enabled',
        'mail_no_entry_days',
        'mail_overtime_enabled',
        'mail_overtime_threshold_min',
        'mail_negative_enabled',
        'mail_negative_threshold_min'
      )
      ->from('ts_user_config')
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($currentUser)));

    $row = $qb->executeQuery()->fetch();
    if ($row === false) {
      return new DataResponse($defaults, Http::STATUS_OK);
    }

    $out = $defaults;

    if (array_key_exists('mail_no_entry_enabled', $row) && $row['mail_no_entry_enabled'] !== null) {
      $out['noEntryEnabled'] = ((int)$row['mail_no_entry_enabled']) === 1;
    }
    if (array_key_exists('mail_no_entry_days', $row) && $row['mail_no_entry_days'] !== null) {
      $out['noEntryDays'] = (int)$row['mail_no_entry_days'];
    }

    if (array_key_exists('mail_overtime_enabled', $row) && $row['mail_overtime_enabled'] !== null) {
      $out['overtimeEnabled'] = ((int)$row['mail_overtime_enabled']) === 1;
    }
    if (array_key_exists('mail_overtime_threshold_min', $row) && $row['mail_overtime_threshold_min'] !== null) {
      $out['overtimeThresholdMinutes'] = (int)$row['mail_overtime_threshold_min'];
    }

    if (array_key_exists('mail_negative_enabled', $row) && $row['mail_negative_enabled'] !== null) {
      $out['negativeOvertimeEnabled'] = ((int)$row['mail_negative_enabled']) === 1;
    }
    if (array_key_exists('mail_negative_threshold_min', $row) && $row['mail_negative_threshold_min'] !== null) {
      $out['negativeOvertimeThresholdMinutes'] = (int)$row['mail_negative_threshold_min'];
    }

    $out['noEntryDays'] = max(1, min(365, (int)$out['noEntryDays']));
    $out['overtimeThresholdMinutes']         = max(0, (int)$out['overtimeThresholdMinutes']);
    $out['negativeOvertimeThresholdMinutes'] = max(0, (int)$out['negativeOvertimeThresholdMinutes']);

    return new DataResponse($out, Http::STATUS_OK);
  }

  /**
   * @NoAdminRequired
   * @NoCSRFRequired
   * @Route("/api/hr/notifications", methods={"PUT"})
   * 
   * Stores email notification settings for the logged-in HR user
   * 
   * Expects JSON body with fields:
   * {
   *   "noEntryEnabled": true,
   *   "noEntryDays": 14,
   *   "overtimeEnabled": false,
   *   "overtimeThresholdMinutes": 600,
   *   "negativeOvertimeEnabled": false,
   *   "negativeOvertimeThresholdMinutes": 600
   * }
   */
  public function setHrNotificationSettings(): DataResponse {
    $currentUser = $this->assertHrUser();

    $payload = $this->request->getParams();
    if (!is_array($payload)) $payload = [];

    $noEntryEnabled = array_key_exists('noEntryEnabled', $payload)
      ? (bool)$payload['noEntryEnabled']
      : self::DEFAULT_NO_ENTRY_ENABLED;

    $noEntryDays = array_key_exists('noEntryDays', $payload)
      ? (int)$payload['noEntryDays']
      : self::DEFAULT_NO_ENTRY_DAYS;

    $overtimeEnabled = array_key_exists('overtimeEnabled', $payload)
      ? (bool)$payload['overtimeEnabled']
      : self::DEFAULT_OVERTIME_ENABLED;

    $overtimeThresholdMinutes = array_key_exists('overtimeThresholdMinutes', $payload)
      ? (int)$payload['overtimeThresholdMinutes']
      : self::DEFAULT_OVERTIME_THRESHOLD;
    
    $negativeOvertimeEnabled = array_key_exists('negativeOvertimeEnabled', $payload)
      ? (bool)$payload['negativeOvertimeEnabled']
      : self::DEFAULT_NEGATIVE_OVERTIME_ENABLED;

    $negativeOvertimeThresholdMinutes = array_key_exists('negativeOvertimeThresholdMinutes', $payload)
      ? (int)$payload['negativeOvertimeThresholdMinutes']
      : self::DEFAULT_NEGATIVE_OVERTIME_THRESHOLD;

    // Clamp
    $noEntryDays = max(1, min(365, $noEntryDays));
    $overtimeThresholdMinutes = max(0, $overtimeThresholdMinutes);
    $negativeOvertimeThresholdMinutes = max(0, $negativeOvertimeThresholdMinutes);

    $qb = $this->db->getQueryBuilder();
    $qb->update('ts_user_config')
      ->set('mail_no_entry_enabled', $qb->createNamedParameter($noEntryEnabled ? 1 : 0))
      ->set('mail_no_entry_days', $qb->createNamedParameter($noEntryDays))
      ->set('mail_overtime_enabled', $qb->createNamedParameter($overtimeEnabled ? 1 : 0))
      ->set('mail_overtime_threshold_min', $qb->createNamedParameter($overtimeThresholdMinutes))
      ->set('mail_negative_enabled', $qb->createNamedParameter($negativeOvertimeEnabled ? 1 : 0))
      ->set('mail_negative_threshold_min', $qb->createNamedParameter($negativeOvertimeThresholdMinutes))
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($currentUser)));

    $affectedRows = $qb->executeStatement();

    if ($affectedRows === 0) {
      // No existing row for this user: insert a new config
      $qbInsert = $this->db->getQueryBuilder();
      $qbInsert->insert('ts_user_config')
        ->values([
          'user_id'      => $qbInsert->createNamedParameter($currentUser),
          'work_minutes' => $qbInsert->createNamedParameter(self::DEFAULT_WORK_MINUTES),
          'state'        => $qbInsert->createNamedParameter(null, \PDO::PARAM_NULL),

          'mail_no_entry_enabled' => $qbInsert->createNamedParameter($noEntryEnabled ? 1 : 0),
          'mail_no_entry_days' => $qbInsert->createNamedParameter($noEntryDays),
          'mail_overtime_enabled' => $qbInsert->createNamedParameter($overtimeEnabled ? 1 : 0),
          'mail_overtime_threshold_min' => $qbInsert->createNamedParameter($overtimeThresholdMinutes),
          'mail_negative_enabled' => $qbInsert->createNamedParameter($negativeOvertimeEnabled ? 1 : 0),
          'mail_negative_threshold_min' => $qbInsert->createNamedParameter($negativeOvertimeThresholdMinutes),
        ]);
      $qbInsert->executeStatement();
    }

    return new DataResponse([
      'noEntryEnabled' => $noEntryEnabled,
      'noEntryDays' => $noEntryDays,
      'overtimeEnabled' => $overtimeEnabled,
      'overtimeThresholdMinutes' => $overtimeThresholdMinutes,
      'negativeOvertimeEnabled' => $negativeOvertimeEnabled,
      'negativeOvertimeThresholdMinutes' => $negativeOvertimeThresholdMinutes,
    ], Http::STATUS_OK);
  }
    
  /**
   * Helper to ensure the current user belongs to the HR group.
   * Returns the current user ID.
  */
  private function assertHrUser() : string {
    $currentUser = $this->userSession->getUser();
    if (!$currentUser) throw new \Exception("Not logged in", Http::STATUS_FORBIDDEN);

    $currentUid = $currentUser->getUID();
    if (!$this->hrService->isHr($currentUid)) throw new \Exception("Access denied", Http::STATUS_FORBIDDEN);

    return $currentUid;
  }

  /**
  * Helper to ensure the current user belongs to the HR group or is the user itself.
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