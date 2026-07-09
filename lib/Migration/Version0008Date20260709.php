<?php

namespace OCA\Timesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0008Date20260709 extends SimpleMigrationStep {
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();

    if (!$schema->hasTable('ts_monthly_balances')) {
      $table = $schema->createTable('ts_monthly_balances');

      $table->addColumn('id', Types::INTEGER, [
        'autoincrement' => true,
        'unsigned' => true,
        'notnull' => true,
      ]);
      $table->setPrimaryKey(['id']);

      $table->addColumn('user_id', Types::STRING, ['length' => 64, 'notnull' => true]);
      $table->addColumn('year', Types::INTEGER, ['notnull' => true]);
      $table->addColumn('month', Types::INTEGER, ['notnull' => true]);

      $table->addColumn('opening_balance_minutes', Types::INTEGER, ['notnull' => true, 'default' => 0]);
      $table->addColumn('target_minutes', Types::INTEGER, ['notnull' => true, 'default' => 0]);
      $table->addColumn('actual_minutes', Types::INTEGER, ['notnull' => true, 'default' => 0]);
      $table->addColumn('delta_minutes', Types::INTEGER, ['notnull' => true, 'default' => 0]);
      $table->addColumn('closing_balance_minutes', Types::INTEGER, ['notnull' => true, 'default' => 0]);

      $table->addColumn('is_closed', Types::INTEGER, ['notnull' => true, 'default' => 0]);
      $table->addColumn('needs_recalculation', Types::INTEGER, ['notnull' => true, 'default' => 0]);
      $table->addColumn('calculated_at', Types::INTEGER, ['notnull' => false]);
      $table->addColumn('updated_at', Types::INTEGER, ['notnull' => true]);

      $table->addUniqueIndex(['user_id', 'year', 'month'], 'ts_mb_unique_user_month');
      $table->addIndex(['user_id', 'year', 'month'], 'ts_mb_user_month');
      $table->addIndex(['needs_recalculation', 'user_id', 'year', 'month'], 'ts_mb_dirty_user_month');
    }

    return $schema;
  }
}
