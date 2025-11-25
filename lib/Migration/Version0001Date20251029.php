<?php

namespace OCA\Timesheet\Migration;

use Closure;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;
use OCP\DB\ISchemaWrapper;

class Version0001Date20251029 extends SimpleMigrationStep {
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();

    if (!$schema->hasTable('ts_entries')) {
      $table = $schema->createTable('ts_entries');

      $table->addColumn('id', 'integer', [
        'autoincrement' => true,
        'unsigned' => true,
        'notnull' => true,
      ]);
      $table->setPrimaryKey(['id']);

      $table->addColumn('user_id', 'string', ['length' => 64, 'notnull' => true]);
      $table->addColumn('work_date', 'date', ['notnull' => true]);

      $table->addColumn('start_min', 'integer', ['notnull' => true]); // Minuten seit 00:00
      $table->addColumn('end_min', 'integer', ['notnull' => true]);
      $table->addColumn('break_minutes', 'integer', ['notnull' => true, 'default' => 0]);

      $table->addColumn('comment', 'text', ['notnull' => false]);

      $table->addColumn('created_at', 'integer', ['notnull' => true]);
      $table->addColumn('updated_at', 'integer', ['notnull' => true]);

      $table->addIndex(['user_id', 'work_date'], 'ts_idx_user_date');
    }

    return $schema;
  }
}