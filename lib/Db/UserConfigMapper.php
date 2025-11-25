<?php 

namespace OCA\Timesheet\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class UserConfigMapper extends QBMapper {
  public function __construct(IDBConnection $db) {
    parent::__construct($db, 'ts_user_config', UserConfig::class);
  }

  /**
  * Find the config entry for a given user.
  * @throws \OCP\AppFramework\Db\DoesNotExistException if not found
  */
  public function findByUser(string $userId): UserConfig {
    $qb = $this->db->getQueryBuilder();
    $qb->select('*')
      ->from('ts_user_config')
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
    return $this->findEntity($qb);
  }

  /**
  * Create or update a user's config (upsert style).
  */
  public function save(UserConfig $config): UserConfig {
    if ($config->getId() !== null) {
      // Existing entry (has an ID) – update it
      return $this->update($config);
    } else {
      // New entry – insert it
      return $this->insert($config);
    }
  }
}