<?php

namespace OCA\Timesheet\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {

  public function __construct(
    private IL10N $l, 
    private IURLGenerator $urlGenerator,
  ) { 
  }

  public function getIcon(): string {
    return $this->urlGenerator->imagePath('timesheet', 'app_dark.svg');
  }

  public function getName(): string {
    return $this->l->t('Timesheet');
  }

  public function getID(): string {
    return 'timesheet';
  }

  public function getPriority(): int {
    return 80;
  }
}