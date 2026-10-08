---
title: omnibill/email
order: 1
---

# omnibill/email

## Installation

```sh
composer require omnibill/email
```

PHP 8.2 or later, `glitchr/omnibill` and `symfony/mailer` (a library: any transport it knows -
SMTP, an API's DSN).

```php
use Omnibill\Email\EmailGatewayFactory;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

$gateway = (new EmailGatewayFactory(new Mailer(Transport::fromDsn('smtp://localhost:25')), $statusStore))
    ->create(['from' => 'Atelier Tilleul <factures@atelier.example>']);
```

## Options

| Option | Default | |
|---|---|---|
| `from` | required | the sender: an address, or `Name <address>` |
| `reply_to` | | where the buyer's answers go |
| `bcc` | `[]` | a copy of each invoice sent: a list, or one comma-separated string |
| `subject` | `Facture {number}` | `{number}`, `{seller}`, `{buyer}`, `{total}`, `{currency}` |
| `body` | "Bonjour, Veuillez trouver ci-joint la facture {number}. Cordialement, {seller}" | the text, the same placeholders |

## What it does

| | |
|---|---|
| `submit()` | an e-mail to `$invoice->buyer->email` (an `InvalidConfigException` without one), the file attached under its name and type (`application/pdf` for Factur-X, `application/xml` otherwise), a `Message-ID` of its own - the flow's reference. Once the mailer took it: `ACCEPTED`, `SENT`. A transport that fails: `UnreachableException`, nothing recorded |
| `setStatus()` | the status recorded under the flow's reference - `PAID`, `REFUSED`, `DISPUTED`... - nothing sent |
| `fetchStatus()` | the statuses recorded, oldest first; the latest as the flow's status |
| `receive`, `lookup`, `report`, `notify` | not supported: there is nobody on the other side to ask |

## The store

Statuses are kept in a `StatusStoreInterface` given to the factory - `record($reference, $change)`,
`history($reference)` - the application's table, usually; `InMemoryStatusStore` when none is given
(right for a test, a script). Nobody else tells an e-mail's statuses: the application records
them, from what it learns (a payment on the bank statement, a refusal by mail).

## Verified, and not

| | |
|---|---|
| An invoice sent for real | **done on 2026-10-08**: the harness's `bare --live` through a local SMTP server (Mailpit, in a throwaway container) - received from `Atelier Tilleul <factures@atelier.example>`, to `compta@erable.example`, subject "Facture F-2026-042", the PDF attached (`application/pdf`), the Message-ID the flow's reference |
| The message, the statuses, the errors | by the tests, on an in-memory mailer |
| Delivery to a real mailbox (SPF, DKIM, spam filters) | not this package's: the application's mailer |
