<?php

namespace OCA\Timesheet\BackgroundJob;

use OCP\BackgroundJob\TimedJob;
use OCP\BackgroundJob\IJob;
use OCP\Mail\IMailer;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserManager;
use OCP\AppFramework\Services\IAppConfig;
use OCA\Timesheet\Service\HrNotificationService;
use OCP\IUser;
use Psr\Log\LoggerInterface;
use OCP\L10N\IFactory;

class HrNotificationJob extends TimedJob {
  private IUserManager $userManager;
  private IMailer $mailer;
  private HrNotificationService $notificationService;
  private ITimeFactory $timeFactory;
  private IAppConfig $config;
  private LoggerInterface $logger;
  private IFactory $l10nFactory;

  public function __construct(ITimeFactory $time,
                              IUserManager $userManager,
                              IMailer $mailer,
                              HrNotificationService $notificationService,
                              IAppConfig $config,
                              LoggerInterface $logger,
                              IFactory $l10nFactory
  ) {
    parent::__construct($time);

    $this->setInterval(24 * 60 * 60); // once a day
    $this->setAllowParallelRuns(false);
    $this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
    
    $this->timeFactory = $time;
    $this->config = $config;
    $this->logger = $logger;
    $this->userManager = $userManager;
    $this->mailer = $mailer;
    $this->notificationService = $notificationService;
    $this->l10nFactory = $l10nFactory;
  }

  protected function run($arguments): void {
    $now = $this->timeFactory->getDateTime('now');

    $force = $this->config->getAppValueString('hr_notification_force', '0') === '1';

    if (!$force && (int)$now->format('N') !== 1) {
      // only run on Mondays unless forced
      return;
    }

    $this->logger->warning("HrNotificationJob RUN", [
      'force' => $force,
      'weekday' => (int)$now->format('N'),
      'date' => $now->format(\DATE_ATOM),
    ]);

    $notificationsByHr = $this->notificationService->doCron();
    if (empty($notificationsByHr)) {
      $this->logger->warning("HrNotificationJob completed (no notifications)");
      return;
    }

    $sent = 0;
    $sendErrors = 0;
    
    foreach ($notificationsByHr as $hrUserId => $data) {
      $hrUser = $this->userManager->get($hrUserId);
      if (!$hrUser) continue;

      $email = $hrUser->getEMailAddress();
      if (empty($email)) continue;

      $l = $this->lForUser($hrUser);

      $subject = $l->t("Timesheet Notifications") . " - " . $now->format('m-d');
      $plainBody = $l->t('Hello %1$s,', [$hrUser->getDisplayName()]) . "\n\n";
      $htmlBody = "<!DOCTYPE html><html><body>";
      $htmlBody .= "<p>" . $l->t('Hello %1$s,', [htmlspecialchars($hrUser->getDisplayName())]) . "</p>";

      $plainBody .= $l->t('In the employee list you oversee, one or more warnings are currently shown. The details are listed below.') . "\n\n";
      $htmlBody .= "<p>" . $l->t('In the employee list you oversee, one or more warnings are currently shown. The details are listed below.') . "</p>";

      if (!empty($data['noEntry'])) {
        $plainBody .= $l->t('No entry for more than %1$s days:', [$data['noEntryDays']]) . "\n";
        $htmlBody .= "<p><strong>" . $l->t('No entry for more than %1$s days:', [$data['noEntryDays']]) . "</strong></p><ul>";
        foreach ($data['noEntry'] as $item) {
          if ($item['days'] === null) {
            $plainBody .= " - " . $l->t('%1$s (more than %2$s days since last entry)', [$item['user'], $data['noEntryDays']]) . "\n";
            $htmlBody .= "<li>" . $l->t('%1$s (more than %2$s days since last entry)', [htmlspecialchars($item['user']), $data['noEntryDays']]) . "</li>";
          } else {
            $plainBody .= " - " . $l->t('%1$s (%2$s days since last entry)', [$item['user'], $item['days']]) . "\n";
            $htmlBody .= "<li>" . $l->t('%1$s (%2$s days since last entry)', [htmlspecialchars($item['user']), $item['days']]) . "</li>";
          }
        }
        $plainBody .= "\n";
        $htmlBody .= "</ul>";
      }
      if (!empty($data['overtime'])) {
        $plainBody .= $l->t('Too much overtime:') . "\n";
        $htmlBody .= "<p><strong>" . $l->t('Too much overtime:') . "</strong></p><ul>";
        foreach ($data['overtime'] as $item) {
          $plainBody .= " - " . $l->t('%1$s (%2$s overtime)', [$item['user'], $item['overtime']]) . "\n";
          $htmlBody .= "<li>" . $l->t('%1$s (%2$s overtime)', [htmlspecialchars($item['user']), $item['overtime']]) . "</li>";
        }
        $plainBody .= "\n";
        $htmlBody  .= "</ul>";
      }
      if (!empty($data['negative'])) {
        $plainBody .= $l->t('Too many negative hours:') . "\n";
        $htmlBody .= "<p><strong>" . $l->t('Too many negative hours:') . "</strong></p><ul>";
        foreach ($data['negative'] as $item) {
          $plainBody .= " - " . $l->t('%1$s (%2$s deficit)', [$item['user'], $item['deficit']]) . "\n";
          $htmlBody .= "<li>" . $l->t('%1$s (%2$s deficit)', [htmlspecialchars($item['user']), $item['deficit']]) . "</li>";
        }
        $plainBody .= "\n";
        $htmlBody  .= "</ul>";
      }

      $plainBody .= "\n" 
        . $l->t('This email was automatically generated based on the notification settings you selected in Nextcloud. Do not reply to this email.') . "\n";
      $htmlBody .= "<hr><p style=\"color:#666;font-size:12px;\">" 
        . $l->t('This email was automatically generated based on the notification settings you selected in Nextcloud. Do not reply to this email.') . "</p>";

      if (trim($plainBody) === $l->t('Hello %1$s,', [$hrUser->getDisplayName()])) {
        continue;
      }

      $htmlBody .= "</body></html>";

      try {
        $message = $this->mailer->createMessage();
        $message->setSubject($subject);
        $message->setTo([$email => $hrUser->getDisplayName()]);
        $message->setPlainBody($plainBody);
        $message->setHtmlBody($htmlBody);
        $this->mailer->send($message);
        $sent++;
      } catch (\Throwable $th) {
        $sendErrors++;
        $this->logger->error("HrNotificationJob: mail send failed", [
          'hrUserId' => $hrUserId,
          'exception' => $th,
        ]);
      }
    }

    $this->logger->warning("HrNotificationJob completed", [
      'sent' => $sent,
      'sendErrors' => $sendErrors,
    ]);
  }

  private function lForUser(IUser $user): \OCP\IL10N {
    $lang = $this->l10nFactory->getUserLanguage($user);
    return $this->l10nFactory->get('timesheet', $lang);
  }
}