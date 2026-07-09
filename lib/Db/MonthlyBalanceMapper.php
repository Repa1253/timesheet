<?php

namespace OCA\Timesheet\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MonthlyBalanceMapper extends QBMapper {
  public function __construct(IDBConnection $db) {
    parent::__construct($db, 'ts_monthly_balances', MonthlyBalance::class);
  }

  public function findByUserMonth(string $userId, int $year, int $month): ?MonthlyBalance {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
      ->from($this->getTableName())
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
      ->andWhere($qb->expr()->eq('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)))
      ->andWhere($qb->expr()->eq('month', $qb->createNamedParameter($month, IQueryBuilder::PARAM_INT)))
      ->setMaxResults(1);

    try {
      return $this->findEntity($qb);
    } catch (DoesNotExistException) {
      return null;
    }
  }

  public function findLatestCleanBefore(string $userId, int $year, int $month): ?MonthlyBalance {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
      ->from($this->getTableName())
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
      ->andWhere($qb->expr()->eq('needs_recalculation', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
      ->andWhere($qb->expr()->orX(
        $qb->expr()->lt('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)),
        $qb->expr()->andX(
          $qb->expr()->eq('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)),
          $qb->expr()->lt('month', $qb->createNamedParameter($month, IQueryBuilder::PARAM_INT))
        )
      ))
      ->orderBy('year', 'DESC')
      ->addOrderBy('month', 'DESC')
      ->setMaxResults(1);

    try {
      return $this->findEntity($qb);
    } catch (DoesNotExistException) {
      return null;
    }
  }

  /** @return MonthlyBalance[] */
  public function findDirtyForUserUpTo(string $userId, int $year, int $month): array {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
      ->from($this->getTableName())
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
      ->andWhere($qb->expr()->eq('needs_recalculation', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
      ->andWhere($qb->expr()->orX(
        $qb->expr()->lt('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)),
        $qb->expr()->andX(
          $qb->expr()->eq('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)),
          $qb->expr()->lte('month', $qb->createNamedParameter($month, IQueryBuilder::PARAM_INT))
        )
      ))
      ->orderBy('year', 'ASC')
      ->addOrderBy('month', 'ASC');

    return $this->findEntities($qb);
  }

  public function markDirtyFromMonth(string $userId, int $year, int $month): void {
    $qb = $this->db->getQueryBuilder();
    $qb->update($this->getTableName())
      ->set('needs_recalculation', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
      ->set('updated_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT))
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
      ->andWhere($qb->expr()->orX(
        $qb->expr()->gt('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)),
        $qb->expr()->andX(
          $qb->expr()->eq('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)),
          $qb->expr()->gte('month', $qb->createNamedParameter($month, IQueryBuilder::PARAM_INT))
        )
      ));
    $qb->executeStatement();
  }

  public function markAllDirtyForUser(string $userId): void {
    $qb = $this->db->getQueryBuilder();
    $qb->update($this->getTableName())
      ->set('needs_recalculation', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
      ->set('updated_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT))
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
    $qb->executeStatement();
  }

  public function markAllDirty(): void {
    $qb = $this->db->getQueryBuilder();
    $qb->update($this->getTableName())
      ->set('needs_recalculation', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
      ->set('updated_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT));
    $qb->executeStatement();
  }

  public function saveCalculated(MonthlyBalance $balance): MonthlyBalance {
    if ($balance->getId() !== null) {
      return $this->update($balance);
    }
    return $this->insert($balance);
  }
}
