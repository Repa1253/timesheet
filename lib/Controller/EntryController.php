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

  #[NoAdminRequired]
  #[NoCSRFRequired]
  public function exportXlsx(?string $month = null, ?string $user = null): DataDownloadResponse {
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
    $monthStr = $month ?: (new \DateTimeImmutable('now'))->format('Y-m');
    $monthDate = \DateTimeImmutable::createFromFormat('Y-m', $monthStr);
    if (!$monthDate) {
      $monthDate = new \DateTimeImmutable(date('Y-m-01'));
      $monthStr = $monthDate->format('Y-m');
    }
    $start = $monthDate->setDate((int)$monthDate->format('Y'), (int)$monthDate->format('m'), 1);
    $end = $start->modify('last day of this month');
    $from = $start->format('Y-m-d');
    $to = $end->format('Y-m-d');

    // 4. load user config
    try {
      $cfg = $this->userConfigMapper->findByUser($targetUid);
    } catch (DoesNotExistException $e) {
      $cfg = null;
    }
    $dailyMinMinutes = $cfg?->getWorkMinutes() ?? 480; // default 8 hours
    $state = $cfg?->getState() ?? '';

    // 5. load entries
    $entries = $this->entryMapper->findByUserAndRange($targetUid, $from, $to);

    $entriesByDate = [];
    foreach ($entries as $entry) {
      $entriesByDate[$entry->getWorkDate()] = $entry;
    }

    // 6. load holidays
    $holidays = [];
    if ($state !== '') {
      $year = (int)$start->format('Y');
      $holidays = $this->holidayService->getHolidays($year, $state);
    }

    // 7. process entries
    foreach ($entriesByDate as $dateStr => $entry) {
      $startMin = $entry->getStartMin();
      $endMin = $entry->getEndMin();
      if ($startMin === null || $endMin === null) {
        continue;
      }
      $breakMin = $entry->getBreakMinutes() ?? 0;
    }

    // 8. generate spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($monthDate->format('F Y'));

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

    // header bold + background
    $sheet->getStyle("A{$row}:J{$row}")->getFont()->setBold(true);
    $sheet->getStyle("A{$row}:J{$row}")
      ->getFill()->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setRGB('EEEEEE');
    $sheet->getStyle("A{$row}:J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $row++;
    $firstDataRow = $row;

    // 9. fill data rows
    for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
      $dateStr = $d->format('Y-m-d');
      
      /** @var Entry|null $entry */
      $entry = $entriesByDate[$dateStr] ?? null;

      // Date and Weekday
      $sheet->setCellValue("A{$row}", Date::PHPToExcel($d->setTime(0,0,0)));
      $sheet->getStyle("A{$row}")->getNumberFormat()->setFormatCode('DD.MM.YYYY');
      $dayIndex = (int)$d->format('w'); // 0 (Sun) to 6 (Sat)
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
        $sheet->getStyle("A{$row}:J{$row}")
          ->getFill()->setFillType(Fill::FILL_SOLID)
          ->getStartColor()->setRGB('F9F9F9');
      }

      $sheet->getStyle("A{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

      $row++;
    }

    $lastDataRow = $row - 1;

    // Set worked hours and overtime values
    $sheet->setCellValue("C{$workedHoursRow}", "=SUM(G{$firstDataRow}:G{$lastDataRow})");
    $sheet->setCellValue("C{$overtimeRow}", "=SUM(H{$firstDataRow}:H{$lastDataRow})");
    $sheet->getStyle("C{$workedHoursRow}:C{$overtimeRow}")
      ->getNumberFormat()->setFormatCode('[HH]:MM');

    // format date column
    $sheet->getStyle("A" . ($headerRow + 1) . ":A" . ($lastDataRow))
      ->getNumberFormat()->setFormatCode('DD.MM.YYYY');

    // format time columns
    $sheet->getStyle("D" . $firstDataRow . ":F" . $lastDataRow)
      ->getNumberFormat()->setFormatCode('HH:MM');

    // format break column
    $sheet->getStyle("E" . $firstDataRow . ":E" . $lastDataRow)
      ->getNumberFormat()->setFormatCode('0');

    // format duration and difference columns
    $sheet->getStyle("G" . $firstDataRow . ":H" . $lastDataRow)
      ->getNumberFormat()->setFormatCode('[HH]:MM');

    // Footer: export timestamp
    $row++;
    $tz = new \DateTimeZone('Europe/Berlin');
    $exportDate = new \DateTimeImmutable('now', $tz);
    $exportLabel = $this->l10n->t('Exported: %s', [$exportDate->format('d.m.Y H:i T')]);
    $sheet->setCellValue("A{$row}", $exportLabel);
    $sheet->mergeCells("A{$row}:C{$row}");

    // Auto size columns
    foreach (range('A', 'J') as $col) {
      $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // 10. save to temp file
    $writer = new Xlsx($spreadsheet);
    ob_start();
    $writer->save('php://output');
    $binary = ob_get_clean();

    $fileName = sprintf('timesheet_%s.xlsx', $targetUid);

    // 11. return response
    return new DataDownloadResponse(
      $binary,
      $fileName,
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
  }
}