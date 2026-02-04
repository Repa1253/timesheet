<?php

namespace OCA\Timesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0007Date20260204 extends SimpleMigrationStep {
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();

    if ($schema->hasTable('ts_user_config')) {
      $table = $schema->getTable('ts_user_config');

      if (!$table->hasColumn('warn_no_entry_days')) {
        $table->addColumn('warn_no_entry_days', Types::INTEGER, [
          'notnull' => false,
          'default' => 14,
        ]);
      }

      if (!$table->hasColumn('warn_overtime_threshold_min')) {
        $table->addColumn('warn_overtime_threshold_min', Types::INTEGER, [
          'notnull' => false,
          'default' => 600,
        ]);
      }

      if (!$table->hasColumn('warn_negative_threshold_min')) {
        $table->addColumn('warn_negative_threshold_min', Types::INTEGER, [
          'notnull' => false,
          'default' => 600,
        ]);
      }
    }

    return $schema;
  }
}
