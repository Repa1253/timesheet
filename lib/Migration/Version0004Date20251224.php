<?php

namespace OCA\Timesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0004Date20251224 extends SimpleMigrationStep {
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();

    if (!$schema->hasTable('ts_entries')) {
      return $schema;
    }

    $table = $schema->getTable('ts_entries');

    if ($table->hasColumn('start_min')) {
      $table->modifyColumn('start_min', [
        'notnull' => false,
        'default' => null,
      ]);
    }

    if ($table->hasColumn('end_min')) {
      $table->modifyColumn('end_min', [
        'notnull' => false,
        'default' => null,
      ]);
    }

    return $schema;
  } 
}