<?php

namespace OCA\Timesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0006Date20260202 extends SimpleMigrationStep {
  public function __construct(private IDBConnection $db) {}

  public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();
    if (!$schema->hasTable('ts_entries')) {
      return;
    }

    $qb = $this->db->getQueryBuilder();
    $qb->select('user_id', 'work_date')
      ->selectAlias($qb->createFunction('COUNT(*)'), 'cnt')
      ->from('ts_entries')
      ->groupBy('user_id')
      ->addGroupBy('work_date')
      ->having($qb->expr()->gt(
        $qb->createFunction('COUNT(*)'),
        $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)
      ));

    $rows = $qb->executeQuery()->fetchAll();

    foreach ($rows as $row) {
      $keepQb = $this->db->getQueryBuilder();
      $keepQb->select('id')
        ->from('ts_entries')
        ->where($keepQb->expr()->eq('user_id', $keepQb->createNamedParameter($row['user_id'])))
        ->andWhere($keepQb->expr()->eq('work_date', $keepQb->createNamedParameter($row['work_date'])))
        ->orderBy('updated_at', 'DESC')
        ->addOrderBy('id', 'DESC')
        ->setMaxResults(1);

      $keepId = $keepQb->executeQuery()->fetchOne();
      if ($keepId === false) {
        continue;
      }

      $delQb = $this->db->getQueryBuilder();
      $delQb->delete('ts_entries')
        ->where($delQb->expr()->eq('user_id', $delQb->createNamedParameter($row['user_id'])))
        ->andWhere($delQb->expr()->eq('work_date', $delQb->createNamedParameter($row['work_date'])))
        ->andWhere($delQb->expr()->neq('id', $delQb->createNamedParameter((int)$keepId, IQueryBuilder::PARAM_INT)));

      $deleted = $delQb->executeStatement();
      if ($deleted > 0) {
        $output->info('Removed duplicate ts_entries rows for ' . $row['user_id'] . ' on ' . $row['work_date']);
      }
    }
  }

  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();

    if (!$schema->hasTable('ts_entries')) {
      return $schema;
    }

    $table = $schema->getTable('ts_entries');

    if ($table->hasIndex('ts_idx_user_date')) {
      $table->dropIndex('ts_idx_user_date');
    }

    if (!$table->hasIndex('ts_unique_user_date')) {
      $table->addUniqueIndex(['user_id', 'work_date'], 'ts_unique_user_date');
    }

    return $schema;
  }
}
