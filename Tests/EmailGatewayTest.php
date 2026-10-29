<?php

namespace Omnibill\Email\Tests;

use Omnibill\Email\EmailGatewayFactory;
use Omnibill\Email\InMemoryStatusStore;
use Omnibill\Exception\InvalidConfigException;
use Omnibill\Exception\UnreachableException;
use Omnibill\GatewayInterface;
use Omnibill\Model\FlowState;
use Omnibill\Model\Invoice;
use Omnibill\Model\LifecycleStatus;
use Omnibill\Model\Party;
use Omnibill\Model\StatusChange;
use Omnibill\Model\Syntax;
use Omnibill\Request\Lookup;
use Omnibill\Request\Receive;
use Omnibill\Request\SetStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class EmailGatewayTest extends TestCase
{
    private Outbox $outbox;
    private InMemoryStatusStore $store;

    private function gateway(array $options = [], bool $down = false): GatewayInterface
    {
        $this->outbox = new Outbox($down);
        $this->store = new InMemoryStatusStore();

        return (new EmailGatewayFactory($this->outbox, $this->store))->create($options + ['from' => 'Atelier Tilleul <factures@atelier.example>']);
    }

    private static function invoice(): Invoice
    {
        return new Invoice('%PDF-1.7 Factur-X', 'F-2026-042.pdf', Syntax::FACTURX, 'F-2026-042', new Party('Atelier Tilleul', '123456789'), new Party('Maison Érable', '987654321', email: 'compta@erable.example'), total: '1250.00');
    }

    public function testTheInvoiceIsAttachedToAnEMailToTheBuyerAndIsSent(): void
    {
        $flow = $this->gateway(['bcc' => 'archives@atelier.example', 'reply_to' => 'compta@atelier.example'])->submit(self::invoice(), 'order-42');

        self::assertCount(1, $this->outbox->sent);
        $email = $this->outbox->sent[0];
        self::assertSame('Facture F-2026-042', $email->getSubject());
        self::assertSame('"Maison Érable" <compta@erable.example>', $email->getTo()[0]->toString());
        self::assertSame(['archives@atelier.example'], array_map(static fn ($a) => $a->getAddress(), $email->getBcc()));
        self::assertStringContainsString("la facture F-2026-042.\n\nCordialement,\nAtelier Tilleul", (string) $email->getTextBody());
        $attachment = $email->getAttachments()[0];
        self::assertSame(['F-2026-042.pdf', 'application/pdf', '%PDF-1.7 Factur-X'], [$attachment->getFilename(), $attachment->getMediaType().'/'.$attachment->getMediaSubtype(), $attachment->getBody()]);

        self::assertSame([FlowState::ACCEPTED, LifecycleStatus::SENT, 'F-2026-042', 'order-42'], [$flow->state, $flow->status, $flow->invoiceNumber, $flow->trackingId]);
        self::assertSame('<'.$flow->reference.'>', $email->getHeaders()->get('Message-ID')->getBodyAsString(), 'the message id names the flow');
    }

    public function testItsStatusesAreKeptByHand(): void
    {
        $gateway = $this->gateway();
        $flow = $gateway->submit(self::invoice());

        $paid = $gateway->setStatus(new StatusChange(LifecycleStatus::PAID, amount: '1250.00'), $flow);
        self::assertSame(LifecycleStatus::PAID, $paid->status);
        $fetched = $gateway->fetchStatus($flow);
        self::assertSame([LifecycleStatus::SENT, LifecycleStatus::PAID], array_map(static fn ($c) => $c->status, $fetched->history));
        self::assertSame(['F-2026-042', '1250.00'], [$fetched->history[1]->invoiceNumber, $fetched->history[1]->amount]);
        self::assertCount(1, $this->outbox->sent, 'nothing sent for a status');
    }

    public function testWhatAnEMailCannotDo(): void
    {
        $gateway = $this->gateway();

        self::assertFalse($gateway->supports(Receive::class));
        self::assertFalse($gateway->supports(Lookup::class));
        self::assertTrue($gateway->supports(SetStatus::class));
        self::assertFalse($gateway->capabilities()->approved);
        try {
            $gateway->submit(new Invoice('x', 'x.xml', Syntax::UBL, 'F-1', buyer: new Party('No e-mail')));
            self::fail();
        } catch (InvalidConfigException $e) {
            self::assertStringContainsString('buyer\'s e-mail', $e->getMessage());
        }
        $this->expectException(UnreachableException::class);
        $this->gateway(down: true)->submit(self::invoice());
    }

    /**
     * A mailer that fails while authenticating: its trace holds the credentials it was sending
     * (SMTP's AUTH PLAIN), which a log of the chain writes when zend.exception_ignore_args is off.
     * The previous is a copy of the mailer's exception, class kept, and no trace keeps arguments.
     */
    public function testNoPartOfAnUnreachableChainHoldsTheMailersCredentials(): void
    {
        $ignoreArgs = ini_set('zend.exception_ignore_args', '0');
        $secret = base64_encode("\0user\0smtp-password-123");
        try {
            (new EmailGatewayFactory(new RefusingMailer(), new InMemoryStatusStore()))->create(['from' => 'a@example.org'])->submit(self::invoice());
            self::fail('no exception');
        } catch (UnreachableException $e) {
            self::assertInstanceOf(TransportException::class, $e->getPrevious());
            for ($one = $e; null !== $one; $one = $one->getPrevious()) {
                self::assertStringNotContainsString($secret, $one->getMessage());
                self::assertStringNotContainsString($secret, print_r($one->getTrace(), true));
            }
        } finally {
            ini_set('zend.exception_ignore_args', false !== $ignoreArgs ? $ignoreArgs : '1');
        }
    }

    public function testAMailerIsNeededAndTheTemplatesAreTheSites(): void
    {
        $flow = $this->gateway(['subject' => '{seller}: invoice {number}, {total} {currency}', 'body' => 'Dear {buyer}, here is {number}.'])->submit(self::invoice());
        self::assertSame('Atelier Tilleul: invoice F-2026-042, 1250.00 EUR', $this->outbox->sent[0]->getSubject());
        self::assertSame('Dear Maison Érable, here is F-2026-042.', $this->outbox->sent[0]->getTextBody());
        self::assertSame('compta@erable.example', $flow->data['to']);

        $this->expectException(InvalidConfigException::class);
        (new EmailGatewayFactory())->create(['from' => 'a@example.org'])->submit(self::invoice());
    }
}

/** The application's mailer, as a test sees it: what was sent, or a transport down. */
final class Outbox implements MailerInterface
{
    /** @var list<Email> */
    public array $sent = [];

    public function __construct(private readonly bool $down = false)
    {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if ($this->down) {
            throw new TransportException('Connection could not be established with host "smtp.example:25".');
        }
        \assert($message instanceof Email);
        $this->sent[] = $message;
    }
}

/** A mailer refused while authenticating, as an SMTP transport is: the command in its trace. */
final class RefusingMailer implements MailerInterface
{
    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $this->command('AUTH PLAIN '.base64_encode("\0user\0smtp-password-123"));
    }

    private function command(string $command): never
    {
        throw new TransportException('Expected response code "235" but got code "535", with message "535 5.7.8 Authentication failed".', 535);
    }
}
