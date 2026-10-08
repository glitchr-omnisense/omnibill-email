<?php

namespace Omnibill\Email;

use Symfony\Component\Mailer\MailerInterface;

/** What the e-mail gateway's actions work with: the mailer, the store, the message's settings. */
final class Api
{
    /** @param list<string> $bcc */
    public function __construct(
        public readonly MailerInterface $mailer,
        public readonly StatusStoreInterface $store,
        public readonly string $from,
        public readonly ?string $replyTo = null,
        public readonly array $bcc = [],
        public readonly string $subject = 'Facture {number}',
        public readonly string $body = "Bonjour,\n\nVeuillez trouver ci-joint la facture {number}.\n\nCordialement,\n{seller}",
    ) {
    }

    /** The subject or the body with {number}, {seller}, {buyer}, {total}, {currency} filled in. */
    public function fill(string $template, array $values): string
    {
        return strtr($template, array_combine(array_map(static fn (string $k) => '{'.$k.'}', array_keys($values)), array_map('strval', $values)));
    }
}
