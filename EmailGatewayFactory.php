<?php

namespace Omnibill\Email;

use Omnibill\Config;
use Omnibill\Email\Action\FetchStatusAction;
use Omnibill\Email\Action\SetStatusAction;
use Omnibill\Email\Action\SubmitAction;
use Omnibill\Exception\InvalidConfigException;
use Omnibill\GatewayFactory;
use Omnibill\Model\Capabilities;
use Omnibill\Model\Syntax;
use Symfony\Component\Mailer\MailerInterface;

/**
 * The invoice by e-mail: attached to a message to the buyer, sent by the
 * application's mailer, its statuses kept by hand. No platform: legal for a
 * small company's invoices until it must issue through an approved platform
 * (1 September 2027 for SMEs and micro-companies); then the site changes
 * gateway, not code.
 *
 *   options:
 *     from: 'Atelier <factures@example.org>'   # required
 *     reply_to: comptabilite@example.org
 *     bcc: [archives@example.org]               # a copy of each invoice sent
 *     subject: 'Facture {number}'               # {number}, {seller}, {buyer}, {total}, {currency}
 *     body: "Bonjour,\n\nVeuillez trouver ci-joint la facture {number}..."
 *
 * The factory takes the application's MailerInterface (symfony/mailer, a
 * library) and the StatusStoreInterface statuses are kept in - this
 * process's memory when none is given.
 */
final class EmailGatewayFactory extends GatewayFactory
{
    public function __construct(private readonly ?MailerInterface $mailer = null, private readonly ?StatusStoreInterface $store = null)
    {
    }

    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnibill.factory_name' => 'email',
            'omnibill.factory_title' => 'E-mail',
            'omnibill.required_options' => ['from'],
            'reply_to' => null,
            'bcc' => [],
            'subject' => 'Facture {number}',
            'body' => "Bonjour,\n\nVeuillez trouver ci-joint la facture {number}.\n\nCordialement,\n{seller}",
            'omnibill.capabilities' => new Capabilities([Syntax::FACTURX, Syntax::UBL, Syntax::CII], approved: false, publicSector: false),
            'omnibill.api' => fn (Config $c) => new Api(
                $this->mailer ?? throw new InvalidConfigException('The "email" gateway needs a mailer: give the factory a Symfony\Component\Mailer\MailerInterface.'),
                $this->store ?? new InMemoryStatusStore(),
                (string) $c['from'],
                '' !== (string) $c['reply_to'] ? (string) $c['reply_to'] : null,
                $c->list('bcc'),
                (string) $c['subject'],
                (string) $c['body'],
            ),
            'omnibill.action.submit' => new SubmitAction(),
            'omnibill.action.fetch_status' => new FetchStatusAction(),
            'omnibill.action.set_status' => new SetStatusAction(),
        ]);
    }
}
