<?php 

namespace OCA\Timesheet\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0002Date20251112 extends SimpleMigrationStep {
  public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
    /** @var ISchemaWrapper $schema */
    $schema = $schemaClosure();

    // Create ts_user_config table if it doesn't exist
    if (!$schema->hasTable('ts_user_config')) {
      $table = $schema->createTable('ts_user_config');
      $table->addColumn('id', 'integer', [
        'autoincrement' => true,
        'unsigned' => true,
        'notnull' => true,
      ]);
      $table->setPrimaryKey(['id']);
      $table->addColumn('user_id', 'string', ['length' => 64, 'notnull' => true]);
      $table->addColumn('work_minutes', 'integer', ['notnull' => true]);
      $table->addUniqueIndex(['user_id'], 'ts_unique_user_config');
    }

    return $schema;
  }
}