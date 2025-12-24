<?php

namespace OCA\Timesheet\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Entry extends Entity implements JsonSerializable {
  /** @var string */
  protected $userId;
  /** @var string YYYY-MM-DD */
  protected $workDate;
  /** @var ?int minutes since midnight */
  protected $startMin;
  /** @var ?int minutes since midnight */
  protected $endMin;
  /** @var int */
  protected $breakMinutes = 0;
  /** @var ?string */
  protected $comment;
  /** @var int */
  protected $createdAt;
  /** @var int */
  protected $updatedAt;

  public function __construct() {
    $this->addType('id', 'integer');
    $this->addType('startMin', 'integer');
    $this->addType('endMin', 'integer');
    $this->addType('breakMinutes', 'integer');
    $this->addType('createdAt', 'integer');
    $this->addType('updatedAt', 'integer');
  }

  public function jsonSerialize(): array {
    return [
      'id'           => $this->getId(),
      'userId'       => $this->userId,
      'workDate'     => $this->workDate,
      'startMin'     => $this->startMin,
      'endMin'       => $this->endMin,
      'breakMinutes' => $this->breakMinutes,
      'comment'      => $this->comment,
      'createdAt'    => $this->createdAt,
      'updatedAt'    => $this->updatedAt,
    ];
  }
}