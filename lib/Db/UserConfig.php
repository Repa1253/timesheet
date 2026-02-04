<?php 

namespace OCA\Timesheet\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * 
 * @method int getWorkMinutes()
 * @method void setWorkMinutes(int $workMinutes)
 * 
 * @method string|null getState()
 * @method void setState(?string $state)
 * 
 * @method bool|null getMailNoEntryEnabled()
 * @method void setMailNoEntryEnabled(?bool $enabled)
 * 
 * @method int|null getMailNoEntryDays()
 * @method void setMailNoEntryDays(?int $days)
 * 
 * @method bool|null getMailOvertimeEnabled()
 * @method void setMailOvertimeEnabled(?bool $enabled)
 * 
 * @method int|null getMailOvertimeThresholdMin()
 * @method void setMailOvertimeThresholdMin(?int $minutes)
 * 
 * @method bool|null getMailNegativeEnabled()
 * @method void setMailNegativeEnabled(?bool $enabled)
 * 
 * @method int|null getMailNegativeThresholdMin()
 * @method void setMailNegativeThresholdMin(?int $minutes)
 *
 * @method int|null getWarnNoEntryDays()
 * @method void setWarnNoEntryDays(?int $days)
 *
 * @method int|null getWarnOvertimeThresholdMin()
 * @method void setWarnOvertimeThresholdMin(?int $minutes)
 *
 * @method int|null getWarnNegativeThresholdMin()
 * @method void setWarnNegativeThresholdMin(?int $minutes)
 */
class UserConfig extends Entity {
  /** @var string */
  protected $userId;
  /** @var int */
  protected $workMinutes;
  /** @var string|null */
  protected $state;
  /** @var bool|null */
  protected $mailNoEntryEnabled;
  /** @var int|null */
  protected $mailNoEntryDays;
  /** @var bool|null */
  protected $mailOvertimeEnabled;
  /** @var int|null */
  protected $mailOvertimeThresholdMin;
  /** @var bool|null */
  protected $mailNegativeEnabled;
  /** @var int|null */
  protected $mailNegativeThresholdMin;
  /** @var int|null */
  protected $warnNoEntryDays;
  /** @var int|null */
  protected $warnOvertimeThresholdMin;
  /** @var int|null */
  protected $warnNegativeThresholdMin;

  public function __construct() {
    $this->addType('id', 'integer');
    $this->addType('workMinutes', 'integer');
    $this->addType('state', 'string');
    $this->addType('mailNoEntryEnabled', 'boolean');
    $this->addType('mailNoEntryDays', 'integer');
    $this->addType('mailOvertimeEnabled', 'boolean');
    $this->addType('mailOvertimeThresholdMin', 'integer');
    $this->addType('mailNegativeEnabled', 'boolean');
    $this->addType('mailNegativeThresholdMin', 'integer');
    $this->addType('warnNoEntryDays', 'integer');
    $this->addType('warnOvertimeThresholdMin', 'integer');
    $this->addType('warnNegativeThresholdMin', 'integer');
  }
}
