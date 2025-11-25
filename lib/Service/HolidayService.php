<?php 

namespace OCA\Timesheet\Service;

use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;

class HolidayService {
  private string $appName;
  private IAppData $appData;
  private IClientService $clientService;

  public function __construct(string $appName, IAppData $appData, IClientService $clientService) {
    $this->appName = $appName;
    $this->appData = $appData;
    $this->clientService = $clientService;
  }

  public function getHolidays(int $year, string $state): array {
    $stateCode = $this->normalizeState($state);

    $cacheFolderName = 'holidays';
    $cacheFileName = $year . '_' . $stateCode . '.json';

    // Sicherstellen, dass der Cache-Ordner existiert
    try {
      $folder = $this->appData->getFolder($cacheFolderName);
    } catch (NotFoundException $e) {
      $folder = $this->appData->newFolder($cacheFolderName);
    }

    // Prüfen, ob bereits ein Cache-Eintrag für Jahr+Bundesland existiert
    if ($folder->fileExists($cacheFileName)) {
      try {
        $file = $folder->getFile($cacheFileName);
        $mtime = $file->getMTime(); // Letzte Änderungszeit des Cache-Files
        if (time() - $mtime < 12 * 60 * 60) {
          $cached = json_decode($file->getContent(), true);
          if (is_array($cached)) {
            return $cached;
          }
        } else {
          // Falls Cache-Inhalt ungültig, löschen um neu zu laden
          $file->delete();
        }
      } catch (\Throwable $th) {
        // Wenn irgendwas mit Cache kaputt ist → neu laden
      }
    }

    return $this->loadAndCacheFromApi($folder, $cacheFileName, $year, $stateCode);
  }

  private function loadAndCacheFromApi($folder, string $cacheKey, int $year, string $stateCode): array {
    $url = 'https://get.api-feiertage.de?years=' . urlencode($year) . '&states=' . urlencode($stateCode);
    try {
      $response = $this->clientService->newClient()->get($url);
      if ($response->getStatusCode() !== 200) {
        throw new \RuntimeException('Feiertage-API antwortete mit HTTP ' . $response->getStatusCode());
      }
      $data = json_decode($response->getBody(), true);
      if (!is_array($data) || !isset($data['feiertage']) || !is_array($data['feiertage'])) {
        throw new \RuntimeException('Unerwartete Antwortstruktur der Feiertage-API');
      }
    } catch (\Throwable $e) {
      throw new \RuntimeException('Feiertage-API Fehler: ' . $e->getMessage() . ' | URL: ' . $url);
    }

    // Nur gesetzliche Feiertage übernehmen
    $result = [];
    foreach ($data['feiertage'] as $holiday) {
      $key = strtolower($stateCode);
      if (!isset($holiday['date']) || !isset($holiday[$key]) || $holiday[$key] !== "1") {
        continue;
      }
      $result[$holiday['date']] = $holiday['fname'] ?? 'Feiertag';
    }

    try {
      $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      $folder->newFile($cacheKey)->putContent($json);
    } catch (\Throwable) {
      // Ignorieren – Caching ist optional
    }

    return $result;
  }

  private function normalizeState(string $state): string {
    $map = [
      'Baden-Württemberg'      => 'bw',
      'Baden Württemberg'      => 'bw',
      'Baden Wuerttemberg'     => 'bw',
      'Bayern'                 => 'by',
      'Berlin'                 => 'be',
      'Brandenburg'            => 'bb',
      'Bremen'                 => 'hb',
      'Hamburg'                => 'hh',
      'Hessen'                 => 'he',
      'Mecklenburg-Vorpommern' => 'mv',
      'Mecklenburg Vorpommern' => 'mv',
      'Niedersachsen'          => 'ni',
      'Nordrhein-Westfalen'    => 'nw',
      'Nordrhein Westfalen'    => 'nw',
      'NRW'                    => 'nw',
      'Rheinland-Pfalz'        => 'rp',
      'Rheinland Pfalz'        => 'rp',
      'Saarland'               => 'sl',
      'Sachsen'                => 'sn',
      'Sachsen-Anhalt'         => 'st',
      'Sachsen Anhalt'         => 'st',
      'Schleswig-Holstein'     => 'sh',
      'Schleswig Holstein'     => 'sh',
      'Thüringen'              => 'th',
    ];

    $state = trim($state);

    // Bereits ein 2-Buchstaben-Code? → Direkt nutzen
    if (preg_match('/^[A-Z]{2}$/', $state)) {
      return strtolower($state);
    }

    return $map[$state] ?? strtolower($state);
  }
}