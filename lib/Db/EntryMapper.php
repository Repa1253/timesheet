<?php

namespace OCA\Timesheet\Db;

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

  public function calculateOvertimeAggregate(string $userId): ?array {
    $qb = $this->db->getQueryBuilder();
    $qb->select('user_id')
       ->selectAlias($qb->createFunction('MIN(`work_date`)'), 'min_date')
       ->selectAlias($qb->createFunction('MAX(`work_date`)'), 'max_date')
       ->selectAlias($qb->createFunction('SUM(`end_min` - `start_min` - `break_minutes`)'), 'total_minutes')
       ->selectAlias($qb->createFunction('COUNT(DISTINCT `work_date`)'), 'total_workdays')
       ->from('ts_entries');
    if ($userId !== null) {
       // Optional: Filter für einen einzelnen Nutzer, falls benötigt
       $qb->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
    }
    $qb->groupBy('user_id');
    $row = $qb->executeQuery()->fetch();
    
    if (!$row || !$row['min_date']) {
      return null;
    }

    // Rückgabe als Array
    return [
        'from'          => $row['min_date'],
        'to'            => $row['max_date'],
        'totalMinutes'  => (int) $row['total_minutes'],
        'totalWorkdays' => (int) $row['total_workdays'],
    ];
  }
}