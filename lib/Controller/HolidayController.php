<?php

namespace OCA\Timesheet\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCA\Timesheet\Service\HolidayService;

/**
* Controller-Klasse mit öffentlichem Endpunkt zum Abrufen der Feiertage.
*/
class HolidayController extends Controller {
  private HolidayService $holidayService;

  public function __construct(string $appName, IRequest $request, HolidayService $holidayService) {
    parent::__construct($appName, $request);
    $this->holidayService = $holidayService;
  }

  /**
  * @NoAdminRequired
  * @NoCSRFRequired
  */
  public function getHolidays(int $year, string $state): JSONResponse {
    try {
      $holidays = $this->holidayService->getHolidays($year, $state);
      // Erfolg: gebe Feiertage als JSON zurück
      return new JSONResponse($holidays);
    } catch (\Throwable $e) {
      // Fehlerfall: Fehlermeldung mit HTTP 500 zurückgeben
      return new JSONResponse(
        [
          'error'  => 'Feiertage konnten nicht abgerufen werden.',
          'detail' => $e->getMessage(), // zum Debuggen einschalten
        ],
        Http::STATUS_INTERNAL_SERVER_ERROR
      );
    }
  }
}