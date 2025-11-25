<?php 

namespace OCA\Timesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0003Date20251112 extends SimpleMigrationStep {
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();

    // Add new column "state" to existing table ts_user_config (if not already present)
    if ($schema->hasTable('ts_user_config')) {
      $table = $schema->getTable('ts_user_config');
      if (!$table->hasColumn('state')) {
        $table->addColumn('state', Types::STRING, [
          'notnull' => false,
          'length' => 64  // e.g. can hold "Bayern" or state code like "BY"
        ]);
      }
    }

    // Drop obsolete table ts_user_holidays if it exists
    if ($schema->hasTable('ts_user_holidays')) {
      $schema->dropTable('ts_user_holidays');
    }

    return $schema;
  }
}