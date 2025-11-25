<?php 

namespace OCA\Timesheet\Db;

use OCP\AppFramework\Db\Entity;

class UserConfig extends Entity {
  /** @var string */
  protected $userId;
  /** @var int */
  protected $workMinutes;
  /** @var string|null */
  protected $state;

  public function __construct() {
    $this->addType('id', 'integer');
    $this->addType('workMinutes', 'integer');
    $this->addType('state', 'string');
  }
}