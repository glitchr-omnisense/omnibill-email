<?php

namespace Omnibill\Email;

use Omnibill\Model\StatusChange;

/**
 * Where the statuses of invoices sent by e-mail are kept - by hand: nobody
 * else tells them. The application's table, usually; InMemoryStatusStore
 * for tests and scripts.
 */
interface StatusStoreInterface
{
    /** Adds a status to the invoice's history, under the flow's reference. */
    public function record(string $reference, StatusChange $change): void;

    /** @return list<StatusChange> oldest first; empty for an unknown reference */
    public function history(string $reference): array;
}
