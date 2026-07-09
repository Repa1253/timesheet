<?php

namespace OCA\Timesheet\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getYear()
 * @method void setYear(int $year)
 * @method int getMonth()
 * @method void setMonth(int $month)
 * @method int getOpeningBalanceMinutes()
 * @method void setOpeningBalanceMinutes(int $minutes)
 * @method int getTargetMinutes()
 * @method void setTargetMinutes(int $minutes)
 * @method int getActualMinutes()
 * @method void setActualMinutes(int $minutes)
 * @method int getDeltaMinutes()
 * @method void setDeltaMinutes(int $minutes)
 * @method int getClosingBalanceMinutes()
 * @method void setClosingBalanceMinutes(int $minutes)
 * @method bool getIsClosed()
 * @method void setIsClosed(bool $isClosed)
 * @method bool getNeedsRecalculation()
 * @method void setNeedsRecalculation(bool $needsRecalculation)
 * @method int|null getCalculatedAt()
 * @method void setCalculatedAt(?int $calculatedAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class MonthlyBalance extends Entity implements JsonSerializable {
  protected $userId;
  protected $year;
  protected $month;
  protected $openingBalanceMinutes = 0;
  protected $targetMinutes = 0;
  protected $actualMinutes = 0;
  protected $deltaMinutes = 0;
  protected $closingBalanceMinutes = 0;
  protected $isClosed = false;
  protected $needsRecalculation = false;
  protected $calculatedAt;
  protected $updatedAt;

  public function __construct() {
    $this->addType('id', 'integer');
    $this->addType('year', 'integer');
    $this->addType('month', 'integer');
    $this->addType('openingBalanceMinutes', 'integer');
    $this->addType('targetMinutes', 'integer');
    $this->addType('actualMinutes', 'integer');
    $this->addType('deltaMinutes', 'integer');
    $this->addType('closingBalanceMinutes', 'integer');
    $this->addType('isClosed', 'boolean');
    $this->addType('needsRecalculation', 'boolean');
    $this->addType('calculatedAt', 'integer');
    $this->addType('updatedAt', 'integer');
  }

  public function jsonSerialize(): array {
    return [
      'id' => $this->getId(),
      'userId' => $this->userId,
      'year' => $this->year,
      'month' => $this->month,
      'openingBalanceMinutes' => $this->openingBalanceMinutes,
      'targetMinutes' => $this->targetMinutes,
      'actualMinutes' => $this->actualMinutes,
      'deltaMinutes' => $this->deltaMinutes,
      'closingBalanceMinutes' => $this->closingBalanceMinutes,
      'isClosed' => (bool)$this->isClosed,
      'needsRecalculation' => (bool)$this->needsRecalculation,
      'calculatedAt' => $this->calculatedAt,
      'updatedAt' => $this->updatedAt,
    ];
  }
}
