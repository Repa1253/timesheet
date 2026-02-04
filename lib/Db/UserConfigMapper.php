<?php 

namespace OCA\Timesheet\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use OCP\DB\QueryBuilder\IQueryBuilder;

class UserConfigMapper extends QBMapper {
  public function __construct(IDBConnection $db) {
    parent::__construct($db, 'ts_user_config', UserConfig::class);
  }

  /**
   * @return array{dailyMin: int|null, state: string|null}
   */
  public function getConfigData(string $userId): array {
    $qb = $this->db->getQueryBuilder();
    $qb->select('work_minutes', 'state')
      ->from('ts_user_config')
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

    $row = $qb->executeQuery()->fetch();

    if ($row === false) {
      return ['dailyMin' => null, 'state' => null];
    }

    return [
      'dailyMin' => (int)$row['work_minutes'],
      'state'    => $row['state'],
    ];
  }

  public function upsertConfigData(string $userId, int $dailyMin, string $state): array {
    // Try to update
    $qb = $this->db->getQueryBuilder();
    $qb->update('ts_user_config')
      ->set('work_minutes', $qb->createNamedParameter($dailyMin, IQueryBuilder::PARAM_INT))
      ->set('state', $qb->createNamedParameter($state, IQueryBuilder::PARAM_STR))
      ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

    $affectedRows = $qb->executeStatement();

    // If no rows were affected -> insert new
    if ($affectedRows === 0) {
      $qbInsert = $this->db->getQueryBuilder();
      $qbInsert->insert('ts_user_config')
        ->values([
          'user_id'      => $qbInsert->createNamedParameter($userId, IQueryBuilder::PARAM_STR),
          'work_minutes' => $qbInsert->createNamedParameter($dailyMin, IQueryBuilder::PARAM_INT),
          'state'        => $qbInsert->createNamedParameter($state, IQueryBuilder::PARAM_STR),
        ]);
      
      $qbInsert->executeStatement();
    }

    return ['dailyMin' => $dailyMin, 'state' => $state];
  }

  /**
   * @param string[] $userIds
   * @return array<string, array{dailyMin: int|null, state: string|null}>
   */
  public function getConfigDataForUsers(array $userIds): array {
    if ($userIds === []) {
      return [];
    }

    $qb = $this->db->getQueryBuilder();
    $qb->select('user_id', 'work_minutes', 'state')
      ->from('ts_user_config')
      ->where($qb->expr()->in('user_id', $qb->createNamedParameter($userIds, IQueryBuilder::PARAM_STR_ARRAY)));

    $rows = $qb->executeQuery()->fetchAll();
    if (!$rows) {
      return [];
    }

    $out = [];
    foreach ($rows as $row) {
      $uid = (string)($row['user_id'] ?? '');
      if ($uid === '') continue;
      $out[$uid] = [
        'dailyMin' => isset($row['work_minutes']) ? (int)$row['work_minutes'] : null,
        'state' => $row['state'] ?? null,
      ];
    }
    return $out;
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
