<?php

namespace OCA\Timesheet\Service;

use OCA\Timesheet\Db\EntryMapper;
use OCA\Timesheet\Db\UserConfigMapper;
use OCA\Timesheet\Service\HrService;
use OCP\AppFramework\Db\DoesNotExistException;

class HrNotificationService {
  private const DEFAULT_NO_ENTRY_DAYS = 14;
  private const DEFAULT_OVERTIME_THRESHOLD_MIN = 600; // 10h
  private const DEFAULT_NEGATIVE_THRESHOLD_MIN = 600; // 10h
  private const DEFAULT_WORK_MINUTES = 480; // 8h

  private array $overtimeCache = [];
  private array $dailyMinCache = [];

  public function __construct(
    private EntryMapper $entryMapper,
    private UserConfigMapper $userConfigMapper,
    private HrService $hrService
  ) {}
  
  public function doCron(): array {
    $result = [];

    $hrUserIds = $this->hrService->getAllHrUserIds();
    if (!$hrUserIds) return $result;

    $todayStr = date('Y-m-d');

    foreach ($hrUserIds as $hrUserId) {
      $hrConfig = null;
      try {
        $hrConfig = $this->userConfigMapper->findByUser($hrUserId);
      } catch (DoesNotExistException) {
        // no config, use defaults
      }

      $noEntryEnabled = (bool)($hrConfig?->getMailNoEntryEnabled() ?? false);
      $overtimeEnabled = (bool)($hrConfig?->getMailOvertimeEnabled() ?? false);
      $negativeEnabled = (bool)($hrConfig?->getMailNegativeEnabled() ?? false);

      if (!$noEntryEnabled && !$overtimeEnabled && !$negativeEnabled) {
        continue;
      }

      $noEntryDays = (int)($hrConfig?->getMailNoEntryDays() ?? self::DEFAULT_NO_ENTRY_DAYS);
      $noEntryDays = max(1, min(365, $noEntryDays));

      $overtimeThresholdMin = (int)($hrConfig?->getMailOvertimeThresholdMin() ?? self::DEFAULT_OVERTIME_THRESHOLD_MIN);
      $overtimeThresholdMin = max(0, $overtimeThresholdMin);

      $negativeThresholdMin = (int)($hrConfig?->getMailNegativeThresholdMin() ?? self::DEFAULT_NEGATIVE_THRESHOLD_MIN);
      $negativeThresholdMin = max(0, $negativeThresholdMin);

      $hrData = [
        'noEntry' => [],
        'noEntryDays' => $noEntryDays,
        
        'overtime' => [],
        'negative' => [],
      ];

      $users = $this->hrService->getAccessibleUsers($hrUserId);
      if (!$users) continue;

      foreach ($users as $userInfo) {
        $userId = (string)($userInfo['id'] ?? '');
        if ($userId === '') continue;

        $userName = (string)($userInfo['name'] ?? $userId);

        if (!array_key_exists($userId, $this->overtimeCache)) {
          $this->overtimeCache[$userId] = $this->entryMapper->calculateOvertimeAggregate($userId);
        }
        $agg = $this->overtimeCache[$userId];

        $lastEntryDate = ($agg && isset($agg['to'])) ? (string)$agg['to'] : null;

        $daysSince = null;
        if ($lastEntryDate) {
          $diffSeconds = strtotime($todayStr) - strtotime($lastEntryDate);
          $daysSince = ($diffSeconds < 0) ? 0 : (int)floor($diffSeconds / 86400);
        }

        if ($noEntryEnabled) {
          if ($lastEntryDate === null || ($daysSince !== null && $daysSince >= $noEntryDays)) {
            $hrData['noEntry'][] = [
              'user' => $userName,
              'days' => ($lastEntryDate && $daysSince !== null) ? (int)$daysSince : null,
            ];
          }
        }

        if (!$agg) continue;

        if (!array_key_exists($userId, $this->dailyMinCache)) {
          $daily = self::DEFAULT_WORK_MINUTES;
          try {
            $userCfg = $this->userConfigMapper->findByUser($userId);
            $wm = (int)($userCfg->getWorkMinutes() ?? self::DEFAULT_WORK_MINUTES);
            $daily = $wm > 0 ? $wm : self::DEFAULT_WORK_MINUTES;
          } catch (DoesNotExistException) {
            // use default
          }
          $this->dailyMinCache[$userId] = $daily;
        }
        $dailyMinutes = (int)$this->dailyMinCache[$userId];

        $totalMinutes = (int)($agg['totalMinutes'] ?? 0);
        $totalWorkdays = (int)($agg['totalWorkdays'] ?? 0);

        $overtimeMinutes = $totalMinutes - ($totalWorkdays * $dailyMinutes);

        if ($overtimeEnabled && $overtimeMinutes > $overtimeThresholdMin) {
          $hrData['overtime'][] = [
            'user' => $userName,
            'overtime' => $this->formatHours($overtimeMinutes, true),
          ];
        }

        if ($negativeEnabled && $overtimeMinutes < -$negativeThresholdMin) {
          $hrData['negative'][] = [
            'user' => $userName,
            'deficit' => $this->formatHours($overtimeMinutes, true),
          ];
        }
      }

      if (!empty($hrData['noEntry']) || !empty($hrData['overtime']) || !empty($hrData['negative'])) {
        $result[$hrUserId] = $hrData;
      }
    }

    return $result;
  }

  private function formatHours(int $minutes, bool $forceSign = false): string {
    $sign = '';
    if ($minutes < 0) {
      $sign = '-';
      $minutes = -$minutes;
    } elseif ($forceSign && $minutes > 0) {
      $sign = '+';
    }
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;
    return $sign . sprintf("%02d:%02d", $hours, $mins);
  }
}