<?php

namespace OCA\Timesheet\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class EntryMapper extends QBMapper {
  public function __construct(IDBConnection $db) {
    parent::__construct($db, 'ts_entries', Entry::class);
  }

  /** @return Entry */
  public function findById(int $id): Entry {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
      ->from($this->getTableName())
      ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
      ->setMaxResults(1);
    return $this->findEntity($qb);
  }

  /** @return ?Entry */
  public function findByUserAndDate(string $userId, string $workDate): ?Entry {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
      ->from($this->getTableName())
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
      ->andWhere($qb->expr()->eq('work_date', $qb->createNamedParameter($workDate)))
      ->setMaxResults(1);
    
    try {
      return $this->findEntity($qb);
    } catch (DoesNotExistException $e) {
      return null;
    }
  }

  /**
   * Create/Update entry with comment only.
   * If entry exists: update comment only
   * If not: create new entry with NULL times and 0 break
   */
  public function upsertCommentOnly(string $userId, string $workDate, string $comment): Entry {
    $existingEntry = $this->findByUserAndDate($userId, $workDate);
    $now = time();

    if ($existingEntry) {
      $existingEntry->setComment($comment);
      $existingEntry->setUpdatedAt($now);
      return $this->update($existingEntry);
    }

    $newEntry = new Entry();
    $newEntry->setUserId($userId);
    $newEntry->setWorkDate($workDate);
    $newEntry->setStartMin(null);
    $newEntry->setEndMin(null);
    $newEntry->setBreakMinutes(0);
    $newEntry->setComment($comment);
    $newEntry->setCreatedAt($now);
    $newEntry->setUpdatedAt($now);

    return $this->insert($newEntry);
  }

  /** @return Entry[] */
  public function findByUserAndRange(string $userId, string $fromYmd, string $toYmd): array {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
      ->from($this->getTableName())
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
      ->andWhere($qb->expr()->gte('work_date', $qb->createNamedParameter($fromYmd)))
      ->andWhere($qb->expr()->lte('work_date', $qb->createNamedParameter($toYmd)))
      ->orderBy('work_date', 'DESC')
      ->addOrderBy('start_min', 'DESC');

    return $this->findEntities($qb);
  }

  /** @return Entry[] */
  public function findAllInRange(string $fromYmd, string $toYmd): array {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
      ->from($this->getTableName())
      ->where($qb->expr()->gte('work_date', $qb->createNamedParameter($fromYmd)))
      ->andWhere($qb->expr()->lte('work_date', $qb->createNamedParameter($toYmd)))
      ->orderBy('work_date', 'DESC')
      ->addOrderBy('start_min', 'DESC');

    return $this->findEntities($qb);
  }

  public function findDistinctUserIds(): array {
    $qb = $this->db->getQueryBuilder();
    $qb->selectDistinct('user_id')
      ->from($this->getTableName())
      ->orderBy('user_id', 'ASC');
    return $qb->executeQuery()->fetchAll();
  }

  public function calculateOvertimeAggregate(string $userId, bool $excludeSpecialDays = false): ?array {
    $qb = $this->db->getQueryBuilder();

    $deltaExpr = '((CASE WHEN end_min < start_min THEN end_min + 1440 ELSE end_min END) - start_min - COALESCE(break_minutes, 0))';
    $totalMinutesExpr  = 'SUM(CASE WHEN start_min IS NULL OR end_min IS NULL THEN 0 ELSE CASE WHEN ' . $deltaExpr . ' < 0 THEN 0 ELSE ' . $deltaExpr . ' END END)';
    $totalWorkdaysExpr = 'SUM(CASE WHEN start_min IS NULL OR end_min IS NULL THEN 0 ELSE 1 END)';

    if ($excludeSpecialDays) {
      $totalWorkdaysExpr = 'SUM(CASE WHEN start_min IS NULL OR end_min IS NULL THEN 0 WHEN WEEKDAY(`work_date`) >= 5 THEN 0 ELSE 1 END)';
    }

    $qb->select('user_id')
      ->selectAlias($qb->createFunction('MIN(`work_date`)'), 'min_date')
      ->selectAlias($qb->createFunction('MAX(`work_date`)'), 'max_date')
      ->selectAlias($qb->createFunction($totalMinutesExpr), 'total_minutes')
      ->selectAlias($qb->createFunction($totalWorkdaysExpr), 'total_workdays')
      ->from('ts_entries');

    $qb->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
    $qb->andWhere($qb->expr()->isNotNull('start_min'));
    $qb->andWhere($qb->expr()->isNotNull('end_min'));
    $qb->groupBy('user_id');

    $row = $qb->executeQuery()->fetch();
    
    if (!$row || !$row['min_date']) {
      return null;
    }

    return [
      'from'          => $row['min_date'],
      'to'            => $row['max_date'],
      'totalMinutes'  => (int) $row['total_minutes'],
      'totalWorkdays' => (int) $row['total_workdays'],
    ];
  }

  /**
   * @param string[] $userIds
   * @return array<string, array{from:string,to:string,totalMinutes:int,totalWorkdays:int}>
   */
  public function calculateOvertimeAggregatesForUsers(array $userIds, bool $excludeSpecialDays): array {
    if ($userIds === []) {
      return [];
    }

    $qb = $this->db->getQueryBuilder();

    $deltaExpr = '((CASE WHEN end_min < start_min THEN end_min + 1440 ELSE end_min END) - start_min - COALESCE(break_minutes, 0))';
    $totalMinutesExpr  = 'SUM(CASE WHEN start_min IS NULL OR end_min IS NULL THEN 0 ELSE CASE WHEN ' . $deltaExpr . ' < 0 THEN 0 ELSE ' . $deltaExpr . ' END END)';
    $totalWorkdaysExpr = 'SUM(CASE WHEN start_min IS NULL OR end_min IS NULL THEN 0 ELSE 1 END)';

    if ($excludeSpecialDays) {
      $totalWorkdaysExpr = 'SUM(CASE WHEN start_min IS NULL OR end_min IS NULL THEN 0 WHEN WEEKDAY(`work_date`) >= 5 THEN 0 ELSE 1 END)';
    }

    $qb->select('user_id')
      ->selectAlias($qb->createFunction('MIN(`work_date`)'), 'min_date')
      ->selectAlias($qb->createFunction('MAX(`work_date`)'), 'max_date')
      ->selectAlias($qb->createFunction($totalMinutesExpr), 'total_minutes')
      ->selectAlias($qb->createFunction($totalWorkdaysExpr), 'total_workdays')
      ->from('ts_entries')
      ->where($qb->expr()->in('user_id', $qb->createNamedParameter($userIds, IQueryBuilder::PARAM_STR_ARRAY)))
      ->andWhere($qb->expr()->isNotNull('start_min'))
      ->andWhere($qb->expr()->isNotNull('end_min'))
      ->groupBy('user_id');

    $rows = $qb->executeQuery()->fetchAll();
    if (!$rows) {
      return [];
    }

    $out = [];
    foreach ($rows as $row) {
      $uid = (string)($row['user_id'] ?? '');
      if ($uid === '' || !$row['min_date']) continue;
      $out[$uid] = [
        'from' => (string)$row['min_date'],
        'to' => (string)$row['max_date'],
        'totalMinutes' => (int)$row['total_minutes'],
        'totalWorkdays' => (int)$row['total_workdays'],
      ];
    }

    return $out;
  }

  /**
   * @param string[] $userIds
   * @return array<string, string>
   */
  public function getLastEntryDatesForUsers(array $userIds, string $fromYmd, string $toYmd): array {
    if ($userIds === []) {
      return [];
    }

    $qb = $this->db->getQueryBuilder();
    $qb->select('user_id')
      ->selectAlias($qb->createFunction('MAX(`work_date`)'), 'last_date')
      ->from($this->getTableName())
      ->where($qb->expr()->in('user_id', $qb->createNamedParameter($userIds, IQueryBuilder::PARAM_STR_ARRAY)))
      ->andWhere($qb->expr()->gte('work_date', $qb->createNamedParameter($fromYmd)))
      ->andWhere($qb->expr()->lte('work_date', $qb->createNamedParameter($toYmd)))
      ->andWhere($qb->expr()->isNotNull('start_min'))
      ->andWhere($qb->expr()->isNotNull('end_min'))
      ->groupBy('user_id');

    $rows = $qb->executeQuery()->fetchAll();
    if (!$rows) {
      return [];
    }

    $out = [];
    foreach ($rows as $row) {
      $uid = (string)($row['user_id'] ?? '');
      $last = (string)($row['last_date'] ?? '');
      if ($uid === '' || $last === '') continue;
      $out[$uid] = $last;
    }
    return $out;
  }

  public function countWorkdaysOnDates(string $userId, array $ymdDates): int {
    if ($ymdDates === []) {
      return 0;
    }

    $qb = $this->db->getQueryBuilder();
    $qb->selectAlias($qb->createFunction('COUNT(*)'), 'cnt')
      ->from($this->getTableName())
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
      ->andWhere($qb->expr()->in('work_date', $qb->createNamedParameter(array_values($ymdDates), IQueryBuilder::PARAM_STR_ARRAY)))
      ->andWhere($qb->expr()->isNotNull('start_min'))
      ->andWhere($qb->expr()->isNotNull('end_min'))
      ->andWhere('WEEKDAY(`work_date`) < 5');

    $row = $qb->executeQuery()->fetch();
    return (int) $row['cnt'] ?? 0;
  }
}
