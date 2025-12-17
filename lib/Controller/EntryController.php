<?php

namespace OCA\Timesheet\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

use OCA\Timesheet\Db\EntryMapper;
use OCA\Timesheet\Db\UserConfigMapper;
use OCA\Timesheet\Service\EntryService;
use OCA\Timesheet\Service\HrService;
use OCA\Timesheet\Service\HolidayService;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

use OC\ForbiddenException;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IL10N;

class EntryController extends Controller {

  public function __construct(
    string $appName,
    IRequest $request,
    private EntryMapper $entryMapper,
    private UserConfigMapper $userConfigMapper,
    private EntryService $service,
    private IUserSession $userSession,
    private IL10N $l10n,
    private HrService $hrService,
    private HolidayService $holidayService,
  ) {
    parent::__construct($appName, $request);
  }

  #[NoAdminRequired]
  public function index(?string $from = null, ?string $to = null, ?string $user = null): DataResponse {
    $from ??= date('Y-m-01');
    $to ??= date('Y-m-t');

    $currentUser = $this->userSession->getUser()->getUID();

    if ($user !== null && $this->hrService->isHr()) {
      $rows = $this->entryMapper->findByUserAndRange($user, $from, $to);
      return new DataResponse($rows);
    }

    $rows = $this->entryMapper->findByUserAndRange($currentUser, $from, $to);
    return new DataResponse($rows);
  }

  #[NoAdminRequired]
  public function create(string $workDate, string $start, string $end, int $breakMinutes = 0, ?string $comment = null): DataResponse {
    $current = $this->userSession->getUser();
    if (!$current) {
      return new DataResponse(['error' => 'Unauthorized'], 401);
    }
    $currentUid = $current->getUID();
    $targetUid = $currentUid;
    $userParam = $this->request->getParam('user');
    if ($userParam !== null && $this->hrService->isHr($currentUid)) {
      $targetUid = $userParam;
    }

    $payload = [
      'workDate' => $workDate,
      'startMin' => self::hmToMin($start),
      'endMin' => self::hmToMin($end),
      'breakMinutes' => $breakMinutes,
      'comment' => $comment,
    ];

    $entry = $this->service->create($payload, $targetUid);
    return new DataResponse($entry);
  }

  #[NoAdminRequired]
  public function update(int $id, ?string $workDate = null, ?string $start = null, ?string $end = null, ?int $breakMinutes = null, ?string $comment = null): DataResponse {
    $data = [];
    if ($workDate !== null) $data['workDate'] = $workDate;
    if ($start !== null)    $data['startMin'] = self::hmToMin($start);
    if ($end !== null)      $data['endMin']   = self::hmToMin($end);
    if ($breakMinutes !== null) $data['breakMinutes'] = $breakMinutes;
    if ($comment !== null)  $data['comment']  = $comment;
    $entry = $this->service->update($id, $data, $this->hrService->isHr());
    return new DataResponse($entry);
  }

  #[NoAdminRequired]
  public function delete(int $id): DataResponse {
    $this->service->delete($id, $this->hrService->isHr());
    return new DataResponse(['ok' => true]);
  }

  private static function hmToMin(string $hm): int {
    [$h, $m] = array_map('intval', explode(':', $hm));
    return max(0, $h*60 + $m);
  }

  private static function parseMonthStart(?string $monthStr, \DateTimeZone $tz): \DateTimeImmutable {
    $m = trim((string)($monthStr ?? ''));
    if ($m === '') {
      $m = (new \DateTimeImmutable('now', $tz))->format('Y-m');
    }

    $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $m . '-01', $tz);
    if (!$dt) {
      $dt = new \DateTimeImmutable('first day of this month', $tz);
      $dt = $dt->setDate((int)$dt->format('Y'), (int)$dt->format('m'), 1);
    }

    return $dt->setTime(0, 0, 0);
  }

  private function resolveUserLocale(?string $override = null): string {
    $loc = trim((string)($override ?? ''));

    if ($loc === '') {
      if (method_exists($this->l10n, 'getLocaleCode')) {
        $loc = (string)$this->l10n->getLocaleCode();
      } elseif (method_exists($this->l10n, 'getLanguageCode')) {
        $loc = (string)$this->l10n->getLanguageCode();
      }
    }

    if ($loc === '') {
      $al = (string)$this->request->getHeader('Accept-Language');
      if ($al !== '') {
        $first = trim(explode(',', $al)[0]);
        $loc = trim(explode(';', $first)[0]);
      }
    }

    $loc = str_replace('-', '_', $loc);
    return $loc !== '' ? $loc : 'en';
  }

  private function sheetTitleForMonth(\DateTimeImmutable $monthStart, string $locale): string {
    $raw = '';

    if (class_exists(\IntlDateFormatter::class)) {
      $fmt = new \IntlDateFormatter(
        $locale,
        \IntlDateFormatter::NONE,
        \IntlDateFormatter::NONE,
        $monthStart->getTimezone()->getName(),
        \IntlDateFormatter::GREGORIAN,
        'LLLL yyyy'
      );
      if ($fmt) {
        $raw = (string)$fmt->format($monthStart);
      }
    }

    if ($raw === '') {
      $raw = $monthStart->format('MMMM yyyy');
    }

    $title = preg_replace('/[\\\\\\/\\?\\*\\:\\[\\]]/', ' ', $raw);
    $title = trim(preg_replace('/\\s+/', ' ', (string)$title));
    if ($title === '') $title = $monthStart->format('MMMM yyyy');

    return function_exists('mb_substr') ? mb_substr($title, 0, 31, 'UTF-8') : substr($title, 0, 31);
  }

  #[NoAdminRequired]
  #[NoCSRFRequired]
  public function exportXlsx(?string $from = null, ?string $to = null, ?string $user = null): DataDownloadResponse {
    $tz = new \DateTimeZone('Europe/Berlin');

    // 1. determine current user
    $currentUser = $this->userSession->getUser();
    if (!$currentUser) {
      throw new ForbiddenException();
    }
    $currentUid = $currentUser->getUID();

    // 2. determine target user
    $targetUid = $currentUid;
    if ($user !== null && $user !== '') {
      if (!$this->hrService->isHr()) {
        throw new ForbiddenException();
      }
      $targetUid = $user;
    }

    // 3. parse month
    if (trim((string)($from ?? '')) === '' && trim((string)($to ?? '')) == '') {
      $legacyMonth = $this->request->getParam('month');
      if (is_string($legacyMonth) && trim($legacyMonth) !== '') {
        $from = $legacyMonth;
        $to = $legacyMonth;
      }
    }
    if (trim((string)($from ?? '')) === '' && trim((string)($to ?? '')) !== '') {
      $from = trim((string)$to);
    }
    if (trim((string)($to ?? '')) === '' && trim((string)($from ?? '')) !== '') {
      $to = trim((string)$from);
    }

    $fromMonth = self::parseMonthStart($from, $tz);
    $toMonth = self::parseMonthStart($to, $tz);
    if ($fromMonth > $toMonth) {
      [$fromMonth, $toMonth] = [$toMonth, $fromMonth];
    }

    // 4. load user config
    try {
      $cfg = $this->userConfigMapper->findByUser($targetUid);
    } catch (DoesNotExistException $e) {
      $cfg = null;
    }
    $dailyMinMinutes = $cfg?->getWorkMinutes() ?? 480; // default 8 hours
    $state = $cfg?->getState() ?? '';

    // 5. export timestamp
    $exportDate = new \DateTimeImmutable('now', $tz);
    $exportLabel = $this->l10n->t('Exported: %s', [$exportDate->format('d.m.Y H:i T')]);

    // 6. build workbook
    $spreadsheet = new Spreadsheet();
    $holidaysByYear = [];
    $sheetIndex = 0;

    $locale = $this->resolveUserLocale($this->request->getParam('locale'));
    for ($monthStart = $fromMonth; $monthStart <= $toMonth; $monthStart = $monthStart->modify('+1 month')) {
      $start = $monthStart;
      $end   = $start->modify('last day of this month');
      $fromDate = $start->format('Y-m-d');
      $toDate   = $end->format('Y-m-d');

      // load entries for month
      $entries = $this->entryMapper->findByUserAndRange($targetUid, $fromDate, $toDate);
      $entriesByDate = [];
      foreach ($entries as $entry) {
        $entriesByDate[$entry->getWorkDate()] = $entry;
      }

      // load holidays for month
      $holidays = [];
      if ($state !== '') {
        $year = (int)$start->format('Y');
        if (!array_key_exists($year, $holidaysByYear)) {
          $holidaysByYear[$year] = $this->holidayService->getHolidays($year, $state);
        }
        $holidays = $holidaysByYear[$year];
      }
      
      // create sheet
      $sheet = ($sheetIndex === 0) ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet($sheetIndex);
      $sheet->setTitle($this->sheetTitleForMonth($start, $locale));
      $sheetIndex++;
      
      $row = 1;
      
      // Header
      $sheet->setCellValue("A{$row}", $this->l10n->t('Employee'));
      $sheet->mergeCells("A{$row}:B{$row}");
      $sheet->setCellValue("C{$row}", $targetUid);
      $row++;
      
      $sheet->setCellValue("A{$row}", $this->l10n->t('Worked Hours'));
      $sheet->mergeCells("A{$row}:B{$row}");
      $workedHoursRow = $row;
      $row++;
      
      $sheet->setCellValue("A{$row}", $this->l10n->t('Overtime'));
      $sheet->mergeCells("A{$row}:B{$row}");
      $overtimeRow = $row;
      $row++;
      
      $sheet->setCellValue("A{$row}", $this->l10n->t('Daily working time'));
      $sheet->mergeCells("A{$row}:B{$row}");
      $sheet->setCellValue("C{$row}", $dailyMinMinutes / 1440);
      $sheet->getStyle("C{$row}")
      ->getNumberFormat()->setFormatCode('[HH]:MM');
      $dailyRow = $row;
      
      $sheet->getStyle("A1:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
      $row += 2;
      
      // Table header
      $headerRow = $row;
      
      $sheet->setCellValue("A{$row}", $this->l10n->t('Date'));
      $sheet->mergeCells("A{$row}:B{$row}");
      // empty for weekday
      $sheet->setCellValue("C{$row}", $this->l10n->t('Status'));
      $sheet->setCellValue("D{$row}", $this->l10n->t('Start'));
      $sheet->setCellValue("E{$row}", $this->l10n->t('Break (min)'));
      $sheet->setCellValue("F{$row}", $this->l10n->t('End'));
      $sheet->setCellValue("G{$row}", $this->l10n->t('Duration'));
      $sheet->setCellValue("H{$row}", $this->l10n->t('Difference'));
      $sheet->setCellValue("I{$row}", $this->l10n->t('Comment'));
      $sheet->setCellValue("J{$row}", $this->l10n->t('Warning'));

      $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
      $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEEEEE');
      $sheet->getStyle("A{$row}:J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

      $row++; 
      $firstDataRow = $row;
      
      // Fill data rows
      for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
        $dateStr = $d->format('Y-m-d');
        $entry = $entriesByDate[$dateStr] ?? null;
        
        $sheet->setCellValue("A{$row}", Date::PHPToExcel($d->setTime(0,0,0)));
        $sheet->getStyle("A{$row}")->getNumberFormat()->setFormatCode('DD.MM.YYYY');

        $dayIndex = (int)$d->format('w');
        $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $weekdayKey = $weekdays[$dayIndex] ?? '';
        $weekday = $weekdayKey !== '' ? $this->l10n->t($weekdayKey) : '';
        $sheet->setCellValue("B{$row}", $weekday);
        
        $isWeekend = ($dayIndex === 0 || $dayIndex === 6);
        $isHoliday = isset($holidays[$dateStr]);
        
        $status = '';
        if ($isHoliday) {
          $status = $this->l10n->t('Holiday');
        } elseif ($isWeekend) {
          $status = $this->l10n->t('Weekend');
        }
        $sheet->setCellValue("C{$row}", $status);
        
        $startMin = null;
        $endMin = null;
        $breakMin = 0;
        $comment = '';
        
        if ($entry) {
          $startMin = $entry->getStartMin();
          $endMin = $entry->getEndMin();
          $breakMin = $entry->getBreakMinutes() ?? 0;
          $comment = (string)$entry->getComment();
        }
        
        if ($startMin !== null && $endMin !== null) {
          $sheet->setCellValue("D{$row}", $startMin / 1440);
          $sheet->setCellValue("F{$row}", $endMin / 1440);
        } else {
          $sheet->setCellValue("D{$row}", null);
          $sheet->setCellValue("F{$row}", null);
        }
        
        // Break in minutes
        $sheet->setCellValue("E{$row}", $breakMin);
        
        // Duration formula
        $durationFormula = '=IF(AND(D' . $row . '<>"",F' . $row . '<>""),(F' . $row . '-D' . $row . '-E' . $row . '/1440),"")';
        $sheet->setCellValue("G{$row}", $durationFormula);
        
        // Difference formula
        $diffFormula = 
        '=IF(G' . $row . '="","",' .
        'IF(G' . $row . '<$C$' . $dailyRow .
        ',"-"&TEXT($C$' . $dailyRow . '-G' . $row . ',"hh:mm"),' .
        'TEXT(G' . $row . '-$C$' . $dailyRow . ',"hh:mm")' .
        '))';
        $sheet->setCellValue("H{$row}", $diffFormula);
        
        // Comment
        $sheet->setCellValue("I{$row}", $comment);
        
        // Warning formula
        $breakExpr = 'IF(G' . $row . '>TIME(9,0,0),IF(E' . $row . '<45,"Break too short",""),IF(G' . $row . '>TIME(6,0,0),IF(E' . $row . '<30,"Break too short",""),""))';
        $warningFormula = 
        '=IF(G' . $row . '="","",TEXTJOIN(", ",TRUE,' .
        'IF(G' . $row . '>TIME(10,0,0),"Above maximum time",""),' .
        $breakExpr . ',' .
        'IF(AND(WEEKDAY(A' . $row . ',2)=7,G' . $row . '>0),"Sunday work not allowed",""),' .
        'IF(C' . $row . '="Holiday","Holiday work not allowed","")' .
        '))';
        $sheet->setCellValue("J{$row}", $warningFormula);
        
        // Highlight weekends and holidays
        if ($isWeekend || $isHoliday) {
          $sheet->getStyle("A{$row}:J{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9F9F9');
        }
        
        $sheet->getStyle("A{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
      }
      
      $lastDataRow = $row - 1;

      $durSum = "SUM(G{$firstDataRow}:G{$lastDataRow})";
      $days = "COUNT(G{$firstDataRow}:G{$lastDataRow})";
      $exp = "(\$C\${$dailyRow}*{$days})";
      $diff = "({$durSum}-{$exp})";
      
      // Set worked hours and overtime values
      $sheet->setCellValue("C{$workedHoursRow}", "=SUM(G{$firstDataRow}:G{$lastDataRow})");
      $sheet->setCellValue("C{$overtimeRow}", "=IF({$days}=0,\"\",IF({$diff}<0,\"-\"&TEXT(-{$diff},\"[hh]:mm\"),TEXT({$diff},\"[hh]:mm\")))");
      $sheet->getStyle("C{$workedHoursRow}:C{$overtimeRow}")->getNumberFormat()->setFormatCode('[HH]:MM');
      
      // Set number formats
      $sheet->getStyle("A" . ($headerRow + 1) . ":A" . ($lastDataRow))->getNumberFormat()->setFormatCode('DD.MM.YYYY');
      $sheet->getStyle("D" . $firstDataRow . ":F" . $lastDataRow)->getNumberFormat()->setFormatCode('HH:MM');
      $sheet->getStyle("E" . $firstDataRow . ":E" . $lastDataRow)->getNumberFormat()->setFormatCode('0');
      $sheet->getStyle("G" . $firstDataRow . ":H" . $lastDataRow)->getNumberFormat()->setFormatCode('[HH]:MM');
      
      // Export timestamp
      $row++;
      $sheet->setCellValue("A{$row}", $exportLabel);
      $sheet->mergeCells("A{$row}:C{$row}");
      
      // Auto size columns
      foreach (range('A', 'H') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
      }
      $sheet->getColumnDimension('I')->setWidth(40);
      $sheet->getColumnDimension('J')->setWidth(50);
      $sheet->getStyle("I{$firstDataRow}:J{$lastDataRow}")->getAlignment()->setWrapText(true);
    }

    $spreadsheet->setActiveSheetIndex(0);

    // 7. save to temp file
    $writer = new Xlsx($spreadsheet);
    ob_start();
    $writer->save('php://output');
    $binary = ob_get_clean();

    $fromLabel = $fromMonth->format('Y-m');
    $toLabel = $toMonth->format('Y-m');
    $fileName = ($fromLabel === $toLabel)
      ? sprintf($this->l10n->t('Timesheet_%s_%s.xlsx', [$targetUid, $fromLabel]))
      : sprintf($this->l10n->t('Timesheet_%s_%s_to_%s.xlsx', [$targetUid, $fromLabel, $toLabel]));

    // 8. return response
    return new DataDownloadResponse(
      $binary,
      $fileName,
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
  }
}