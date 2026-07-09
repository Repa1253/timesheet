<?php

namespace OCA\Timesheet\Service;

use OCA\Timesheet\Db\Entry;
use OCA\Timesheet\Db\EntryMapper;
use OCP\IUserSession;

class EntryService {
  public function __construct(
    private EntryMapper $mapper,
    private IUserSession $userSession,
    private MonthlyBalanceService $monthlyBalanceService,
  ) {}

  public function create(array $data, ?string $forceUserId = null): Entry {
    $userId = $forceUserId ?? $this->userSession->getUser()->getUID();
    $workDate = (string)$data['workDate'];

    $existing = $this->mapper->findByUserAndDate($userId, $workDate);
    if ($existing) {
      throw new \DomainException('Entry already exists', 409);
    }

    $entry = new Entry();

    $entry->setUserId($userId);
    $entry->setWorkDate($workDate);
    $entry->setStartMin(array_key_exists('startMin', $data) ? $data['startMin'] : null);
    $entry->setEndMin(array_key_exists('endMin', $data) ? $data['endMin'] : null);
    $entry->setBreakMinutes((int)($data['breakMinutes'] ?? 0));
    $entry->setComment($data['comment'] ?? null);
    $entry->setCreatedAt(time());
    $entry->setUpdatedAt(time());

    $created = $this->mapper->insert($entry);
    $this->monthlyBalanceService->markDirtyFromDate($userId, $workDate);
    return $created;
  }

  public function upsertCommentOnly(string $userId, string $workDate, string $comment): Entry {
    $entry = $this->mapper->upsertCommentOnly($userId, $workDate, $comment);
    $this->monthlyBalanceService->markDirtyFromDate($userId, $workDate);
    return $entry;
  }

  public function update(int $id, array $data, bool $isHr = false): Entry {
    /** @var Entry $entry */
    $entry = $this->mapper->findById($id);
    $oldWorkDate = (string)$entry->getWorkDate();

    $currentUser = $this->userSession->getUser()->getUID();
    if (!$isHr && $entry->getUserId() !== $currentUser) {
      throw new \RuntimeException('Not allowed');
    }

    if (array_key_exists('workDate', $data)) {
      $newWorkDate = (string)$data['workDate'];
      if ($newWorkDate !== '' && $newWorkDate !== $entry->getWorkDate()) {
        $existing = $this->mapper->findByUserAndDate($entry->getUserId(), $newWorkDate);
        if ($existing && $existing->getId() !== $entry->getId()) {
          throw new \DomainException('Entry already exists', 409);
        }
      }
    }

    foreach (['workDate', 'startMin', 'endMin', 'breakMinutes', 'comment'] as $k) {
      if (array_key_exists($k, $data)) {
        $setter = 'set'.ucfirst($k);
        $entry->$setter($data[$k]);
      }
    }
    $entry->setUpdatedAt(time());

    $updated = $this->mapper->update($entry);
    $newWorkDate = (string)$updated->getWorkDate();
    $dirtyFrom = ($newWorkDate !== '' && $newWorkDate < $oldWorkDate) ? $newWorkDate : $oldWorkDate;
    $this->monthlyBalanceService->markDirtyFromDate($updated->getUserId(), $dirtyFrom);

    return $updated;
  }

  public function delete(int $id, bool $isHr = false): void {
    /** @var Entry $entry */
    $entry = $this->mapper->findById($id);
    $currentUser = $this->userSession->getUser()->getUID();
    if (!$isHr && $entry->getUserId() !== $currentUser) {
      throw new \RuntimeException('Not allowed');
    }

    $userId = $entry->getUserId();
    $workDate = (string)$entry->getWorkDate();
    $this->mapper->delete($entry);
    $this->monthlyBalanceService->markDirtyFromDate($userId, $workDate);
  }
}
