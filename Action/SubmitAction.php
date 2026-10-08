<?php

namespace Omnibill\Email\Action;

use Omnibill\Action\ActionInterface;
use Omnibill\Action\ApiAwareInterface;
use Omnibill\Action\ApiAwareTrait;
use Omnibill\Email\Api;
use Omnibill\Exception\InvalidConfigException;
use Omnibill\Exception\UnreachableException;
use Omnibill\Model\Direction;
use Omnibill\Model\Flow;
use Omnibill\Model\FlowKind;
use Omnibill\Model\FlowState;
use Omnibill\Model\LifecycleStatus;
use Omnibill\Model\StatusChange;
use Omnibill\Request\Request;
use Omnibill\Request\Submit;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * The invoice, attached to an e-mail to the buyer's address: once the
 * mailer took it, the invoice is SENT - what happens next, the application
 * records by hand (setStatus).
 */
final class SubmitAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Submit;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Submit);
        $invoice = $request->invoice;
        $to = $invoice->buyer?->email ?? throw new InvalidConfigException('The "email" gateway sends an invoice to its buyer\'s e-mail: the Invoice has no buyer, or the buyer no e-mail.');

        $values = ['number' => $invoice->number ?? '', 'seller' => $invoice->seller?->name ?? '', 'buyer' => $invoice->buyer?->name ?? '', 'total' => $invoice->total ?? '', 'currency' => $invoice->currency ?? ''];
        $reference = \sprintf('%s@omnibill', bin2hex(random_bytes(16)));
        $email = (new Email())
            ->from($this->api->from)
            ->to(new Address($to, (string) $invoice->buyer?->name))
            ->subject(trim(preg_replace('~\s+~', ' ', $this->api->fill($this->api->subject, $values))))
            ->text($this->api->fill($this->api->body, $values))
            ->attach($invoice->content, $invoice->filename, $invoice->syntax->mimeType());
        $email->getHeaders()->addIdHeader('Message-ID', $reference);
        if (null !== $this->api->replyTo) {
            $email->replyTo($this->api->replyTo);
        }
        foreach ($this->api->bcc as $bcc) {
            $email->addBcc($bcc);
        }

        try {
            $this->api->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new UnreachableException('email', 'The mailer could not send the invoice: '.$e->getMessage(), null, $e);
        }

        $now = new \DateTimeImmutable();
        $sent = new StatusChange(LifecycleStatus::SENT, $invoice->number, $now);
        $this->api->store->record($reference, $sent);
        $request->setResult(new Flow($reference, FlowKind::INVOICE, Direction::OUT, FlowState::ACCEPTED, $request->trackingId, $invoice->number, $now, $now, LifecycleStatus::SENT, [$sent], name: $invoice->filename, data: ['to' => $to]));
    }
}
