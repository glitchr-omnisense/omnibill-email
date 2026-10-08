<?php

namespace Omnibill\Email\Action;

use Omnibill\Action\ActionInterface;
use Omnibill\Action\ApiAwareInterface;
use Omnibill\Action\ApiAwareTrait;
use Omnibill\Email\Api;
use Omnibill\Request\FetchStatus;
use Omnibill\Request\Request;

/** The invoice's statuses, as the application recorded them. */
final class FetchStatusAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof FetchStatus;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof FetchStatus);
        $history = $this->api->store->history($request->flow->reference);
        $last = $history ? $history[array_key_last($history)] : null;
        $request->setResult($request->flow->with(status: $last?->status, history: $history, updatedAt: $last?->at));
    }
}
