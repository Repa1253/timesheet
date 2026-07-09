<?php

namespace OCA\Timesheet\Service;

use OCA\Timesheet\Db\EntryMapper;
use OCA\Timesheet\Db\MonthlyBalance;
use OCA\Timesheet\Db\MonthlyBalanceMapper;
use OCA\Timesheet\Db\UserConfigMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Services\IAppConfig;

class MonthlyBalanceService {
  private const DEFAULT_WORK_MINUTES = 480;

  public function __construct(
    private EntryMapper $entryMapper,
    private MonthlyBalanceMapper $balanceMapper,
    private UserConfigMapper $userConfigMapper,
    private IAppConfig $appConfig,
    private HolidayService $holidayService,
  ) {}

  public function getMonth(string $userId, int $year, int $month): ?MonthlyBalance {
    return $this->balanceMapper->findByUserMonth($userId, $year, $month);
  }

  public function ensureMonthSnapshot(string $userId, int $year, int $month): MonthlyBalance {
    [$year, $month] = $this->normalizeMonth($year, $month);
    $this->recalculateDirtyForUserUpTo($userId, $year, $month);

    $balance = $this->balanceMapper->findByUserMonth($userId, $year, $month);
    if ($balance === null || (bool)$balance->getNeedsRecalculation()) {
      return $this->calculateAndSaveMonth($userId, $year, $month);
    }

    return $balance;
  }

  public function calculateAndSaveMonth(string $userId, int $year, int $month): MonthlyBalance {
    [$year, $month] = $this->normalizeMonth($year, $month);
    $cfg = $this->getWorkConfig($userId);
    $monthStart = $this->monthStart($year, $month);
    $monthEnd = $this->monthEnd($year, $month);

    $opening = $this->calculateOpeningBalance($userId, $year, $month, $cfg['dailyMin'], $cfg['state']);
    $monthValues = $this->calculateRangeDelta($userId, $monthStart, $monthEnd, $cfg['dailyMin'], $cfg['state']);
    $delta = $monthValues['actualMinutes'] - $monthValues['targetMinutes'];

    $existing = $this->balanceMapper->findByUserMonth($userId, $year, $month);
    $balance = $existing ?? new MonthlyBalance();
    $now = time();

    if ($existing === null) {
      $balance->setUserId($userId);
      $balance->setYear($year);
      $balance->setMonth($month);
    }

    $balance->setOpeningBalanceMinutes($opening);
    $balance->setTargetMinutes($monthValues['targetMinutes']);
    $balance->setActualMinutes($monthValues['actualMinutes']);
    $balance->setDeltaMinutes($delta);
    $balance->setClosingBalanceMinutes($opening + $delta);
    $balance->setIsClosed(false);
    $balance->setNeedsRecalculation(false);
    $balance->setCalculatedAt($now);
    $balance->setUpdatedAt($now);

    return $this->balanceMapper->saveCalculated($balance);
  }

  public function markDirtyFromDate(string $userId, string $workDate): void {
    $year = (int)substr($workDate, 0, 4);
    $month = (int)substr($workDate, 5, 2);
    if ($year > 0 && $month >= 1 && $month <= 12) {
      $this->markDirtyFromMonth($userId, $year, $month);
    }
  }

  public function markDirtyFromMonth(string $userId, int $year, int $month): void {
    [$year, $month] = $this->normalizeMonth($year, $month);
    $this->balanceMapper->markDirtyFromMonth($userId, $year, $month);
  }

  public function markAllDirtyForUser(string $userId): void {
    $this->balanceMapper->markAllDirtyForUser($userId);
  }

  public function markAllDirty(): void {
    $this->balanceMapper->markAllDirty();
  }

  public function recalculateDirtyForUserUpTo(string $userId, int $year, int $month): void {
    [$year, $month] = $this->normalizeMonth($year, $month);
    $dirty = $this->balanceMapper->findDirtyForUserUpTo($userId, $year, $month);
    foreach ($dirty as $balance) {
      $this->calculateAndSaveMonth($userId, (int)$balance->getYear(), (int)$balance->getMonth());
    }
  }

  public function findLatestCleanBefore(string $userId, int $year, int $month): ?MonthlyBalance {
    [$year, $month] = $this->normalizeMonth($year, $month);
    return $this->balanceMapper->findLatestCleanBefore($userId, $year, $month);
  }

  public function calculateCurrentBalance(string $userId): array {
    $today = new \DateTimeImmutable('today');
    $nextMonth = $today->modify('first day of next month');
    $targetYear = (int)$nextMonth->format('Y');
    $targetMonth = (int)$nextMonth->format('m');

    $currentMonth = $today->modify('first day of this month');
    $this->ensureMonthSnapshot($userId, (int)$currentMonth->format('Y'), (int)$currentMonth->format('m'));

    $cfg = $this->getWorkConfig($userId);
    $latest = $this->balanceMapper->findLatestCleanBefore($userId, $targetYear, $targetMonth);
    $opening = 0;
    $from = null;
    $source = 'entries';

    if ($latest !== null) {
      $opening = (int)$latest->getClosingBalanceMinutes();
      $from = $this->monthStart((int)$latest->getYear(), (int)$latest->getMonth())->modify('+1 month')->format('Y-m-d');
      $source = 'snapshot';
    }

    $open = $this->calculateRangeDelta($userId, $from, null, $cfg['dailyMin'], $cfg['state']);
    $overtime = $opening + $open['actualMinutes'] - $open['targetMinutes'];
    $lastEntryDate = $this->entryMapper->getLastEntryDateForUser($userId);

    return [
      'from' => $latest ? sprintf('%04d-%02d-01', (int)$latest->getYear(), (int)$latest->getMonth()) : ($open['from'] ?? $today->format('Y-m-d')),
      'to' => $lastEntryDate ?? ($latest ? $this->monthEnd((int)$latest->getYear(), (int)$latest->getMonth())->format('Y-m-d') : $today->format('Y-m-d')),
      'totalMinutes' => $open['actualMinutes'],
      'totalWorkdays' => $open['workdays'],
      'targetMinutes' => $open['targetMinutes'],
      'dailyMin' => $cfg['dailyMin'],
      'openingBalanceMinutes' => $opening,
      'overtimeMinutes' => $overtime,
      'lastEntryDate' => $lastEntryDate,
      'source' => $source,
      'lastSnapshot' => $latest ? $latest->jsonSerialize() : null,
    ];
  }

  private function calculateOpeningBalance(string $userId, int $year, int $month, int $dailyMin, string $state): int {
    $monthStart = $this->monthStart($year, $month);
    $to = $monthStart->modify('-1 day')->format('Y-m-d');
    $latest = $this->balanceMapper->findLatestCleanBefore($userId, $year, $month);

    if ($latest !== null) {
      $opening = (int)$latest->getClosingBalanceMinutes();
      $from = $this->monthStart((int)$latest->getYear(), (int)$latest->getMonth())->modify('+1 month')->format('Y-m-d');
      if ($from > $to) {
        return $opening;
      }
      $range = $this->calculateRangeDelta($userId, $from, $to, $dailyMin, $state);
      return $opening + $range['actualMinutes'] - $range['targetMinutes'];
    }

    $range = $this->calculateRangeDelta($userId, null, $to, $dailyMin, $state);
    return $range['actualMinutes'] - $range['targetMinutes'];
  }

  /** @return array{actualMinutes:int,targetMinutes:int,workdays:int,from:?string,to:?string} */
  private function calculateRangeDelta(string $userId, string|\DateTimeImmutable|null $from, string|\DateTimeImmutable|null $to, int $dailyMin, string $state): array {
    $fromYmd = $from instanceof \DateTimeImmutable ? $from->format('Y-m-d') : $from;
    $toYmd = $to instanceof \DateTimeImmutable ? $to->format('Y-m-d') : $to;
    if ($toYmd !== null && $fromYmd !== null && $fromYmd > $toYmd) {
      return ['actualMinutes' => 0, 'targetMinutes' => 0, 'workdays' => 0, 'from' => null, 'to' => null];
    }

    $excludeSpecialDays = $this->appConfig->getAppValueString('specialdays_check', '0') === '1';
    $agg = $this->entryMapper->calculateWorkAggregateForRange($userId, $fromYmd, $toYmd, $excludeSpecialDays);
    if (!$agg) {
      return ['actualMinutes' => 0, 'targetMinutes' => 0, 'workdays' => 0, 'from' => null, 'to' => null];
    }

    $workdays = (int)$agg['totalWorkdays'];
    if ($excludeSpecialDays && $state !== '') {
      $holidayDates = $this->getHolidayDates((string)$agg['from'], (string)$agg['to'], $state);
      if ($holidayDates !== []) {
        $workdays = max(0, $workdays - $this->entryMapper->countWorkdaysOnDates($userId, $holidayDates));
      }
    }

    return [
      'actualMinutes' => (int)$agg['totalMinutes'],
      'targetMinutes' => $workdays * $dailyMin,
      'workdays' => $workdays,
      'from' => (string)$agg['from'],
      'to' => (string)$agg['to'],
    ];
  }

  /** @return string[] */
  private function getHolidayDates(string $from, string $to, string $state): array {
    $fromYear = (int)substr($from, 0, 4);
    $toYear = (int)substr($to, 0, 4);
    if ($fromYear <= 0 || $toYear <= 0) return [];

    $dates = [];
    try {
      for ($year = $fromYear; $year <= $toYear; $year++) {
        foreach ($this->holidayService->getHolidays($year, $state) as $date => $_name) {
          if ($date >= $from && $date <= $to) {
            $dates[] = $date;
          }
        }
      }
    } catch (\Throwable) {
      return [];
    }
    return $dates;
  }

  /** @return array{dailyMin:int,state:string} */
  private function getWorkConfig(string $userId): array {
    $dailyMin = self::DEFAULT_WORK_MINUTES;
    $state = '';
    try {
      $cfg = $this->userConfigMapper->findByUser($userId);
      $workMinutes = (int)($cfg->getWorkMinutes() ?? self::DEFAULT_WORK_MINUTES);
      $dailyMin = $workMinutes > 0 ? $workMinutes : self::DEFAULT_WORK_MINUTES;
      $state = trim((string)($cfg->getState() ?? ''));
      if ($state === 'null') $state = '';
    } catch (DoesNotExistException) {
      // defaults
    }
    return ['dailyMin' => $dailyMin, 'state' => $state];
  }

  /** @return array{int,int} */
  private function normalizeMonth(int $year, int $month): array {
    $dt = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, max(1, min(12, $month))));
    return [(int)$dt->format('Y'), (int)$dt->format('m')];
  }

  private function monthStart(int $year, int $month): \DateTimeImmutable {
    return new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
  }

  private function monthEnd(int $year, int $month): \DateTimeImmutable {
    return $this->monthStart($year, $month)->modify('last day of this month');
  }
}
