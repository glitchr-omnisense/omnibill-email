<?php

namespace Omnibill\Email\Action;

use Omnibill\Action\ActionInterface;
use Omnibill\Action\ApiAwareInterface;
use Omnibill\Action\ApiAwareTrait;
use Omnibill\Email\Api;
use Omnibill\Exception\InvalidConfigException;
use Omnibill\Model\StatusChange;
use Omnibill\Request\Request;
use Omnibill\Request\SetStatus;

/**
 * A status recorded by hand: the invoice paid, refused, disputed - nobody
 * else tells it by e-mail. Nothing is sent.
 */
final class SetStatusAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof SetStatus;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof SetStatus);
        $flow = $request->flow ?? throw new InvalidConfigException('The "email" gateway records a status on the flow its invoice was sent in: give the Flow.');
        $change = $request->change;
        $change = new StatusChange($change->status, $change->invoiceNumber ?? $flow->invoiceNumber, $change->at ?? new \DateTimeImmutable(), $change->reason, $change->amount, $change->code, $change->file, $change->filename);
        $this->api->store->record($flow->reference, $change);
        $history = $this->api->store->history($flow->reference);
        $request->setResult($flow->with(status: $change->status, history: $history, updatedAt: $change->at));
    }
}
