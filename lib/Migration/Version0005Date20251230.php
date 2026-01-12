<?php

namespace OCA\Timesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0005Date20251230 extends SimpleMigrationStep {
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();

    if ($schema->hasTable('ts_user_config')) {
      $table = $schema->getTable('ts_user_config');

      if (!$table->hasColumn('mail_no_entry_enabled')) {
        $table->addColumn('mail_no_entry_enabled', Types::INTEGER, [
          'notnull' => false,
          'default' => 0,
        ]);
      }

      if (!$table->hasColumn('mail_no_entry_days')) {
        $table->addColumn('mail_no_entry_days', Types::INTEGER, [
          'notnull' => false,
          'default' => 14,
        ]);
      }

      if (!$table->hasColumn('mail_overtime_enabled')) {
        $table->addColumn('mail_overtime_enabled', Types::INTEGER, [
          'notnull' => false,
          'default' => 0,
        ]);
      }

      if (!$table->hasColumn('mail_overtime_threshold_min')) {
        $table->addColumn('mail_overtime_threshold_min', Types::INTEGER, [
          'notnull' => false,
          'default' => 600,
        ]);
      }

      if (!$table->hasColumn('mail_negative_enabled')) {
        $table->addColumn('mail_negative_enabled', Types::INTEGER, [
          'notnull' => false,
          'default' => 0,
        ]);
      }

      if (!$table->hasColumn('mail_negative_threshold_min')) {
        $table->addColumn('mail_negative_threshold_min', Types::INTEGER, [
          'notnull' => false,
          'default' => 600,
        ]);
      }
    }
    
    return $schema;
  }
}