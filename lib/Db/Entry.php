<?php

namespace OCA\Timesheet\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * 
 * @method string getWorkDate()
 * @method void setWorkDate(string $workDate)
 * 
 * @method ?int getStartMin()
 * @method void setStartMin(?int $startMin)
 * 
 * @method ?int getEndMin()
 * @method void setEndMin(?int $endMin)
 * 
 * @method int getBreakMinutes()
 * @method void setBreakMinutes(int $breakMinutes)
 * 
 * @method ?string getComment()
 * @method void setComment(?string $comment)
 * 
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * 
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
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