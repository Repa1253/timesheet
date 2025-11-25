<?php

namespace OCA\Timesheet\Service;

use OCA\Timesheet\Db\Entry;
use OCA\Timesheet\Db\EntryMapper;
use OCP\IUserSession;

class EntryService {
  public function __construct(
    private EntryMapper $mapper,
    private IUserSession $userSession
  ) {}

  public function create(array $data, ?string $forceUserId = null): Entry {
    $entry = new Entry();
    $userId = $forceUserId ?? $this->userSession->getUser()->getUID();

    $entry->setUserId($userId);
    $entry->setWorkDate($data['workDate']);
    $entry->setStartMin((int)$data['startMin']);
    $entry->setEndMin((int)$data['endMin']);
    $entry->setBreakMinutes((int)($data['breakMinutes'] ?? 0));
    $entry->setComment($data['comment'] ?? null);
    $entry->setCreatedAt(time());
    $entry->setUpdatedAt(time());

    return $this->mapper->insert($entry);
  }

  public function update(int $id, array $data, bool $isHr = false): Entry {
    /** @var Entry $entry */
    $entry = $this->mapper->findById($id);

    $currentUser = $this->userSession->getUser()->getUID();
    if (!$isHr && $entry->getUserId() !== $currentUser) {
      throw new \RuntimeException('Not allowed');
    }

    foreach (['workDate', 'startMin', 'endMin', 'breakMinutes', 'comment'] as $k) {
      if (array_key_exists($k, $data)) {
        $setter = 'set'.ucfirst($k);
        $entry->$setter($data[$k]);
      }
    }
    $entry->setUpdatedAt(time());
    return $this->mapper->update($entry);
  }

  public function delete(int $id, bool $isHr = false): void {
    /** @var Entry $entry */
    $entry = $this->mapper->findById($id);
    $currentUser = $this->userSession->getUser()->getUID();
    if (!$isHr && $entry->getUserId() !== $currentUser) {
      throw new \RuntimeException('Not allowed');
    }
    $this->mapper->delete($entry);
  }
}