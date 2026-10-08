<?php

namespace Omnibill\Email;

use Omnibill\Model\StatusChange;

/** Statuses in this process's memory: for tests, a script, the harness. */
final class InMemoryStatusStore implements StatusStoreInterface
{
    /** @var array<string, list<StatusChange>> */
    private array $histories = [];

    public function record(string $reference, StatusChange $change): void
    {
        $this->histories[$reference][] = $change;
    }

    public function history(string $reference): array
    {
        return $this->histories[$reference] ?? [];
    }
}
