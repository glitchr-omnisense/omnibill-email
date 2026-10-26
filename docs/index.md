# omnibill/email

The invoice by e-mail for [omnisense/omnibill](https://github.com/glitchr-omnisense/omnibill):
attached to a message to the buyer, sent by the application's Symfony Mailer, its statuses kept by
the application. No platform, no API: legal for a small company's invoices until it must issue
through an approved platform (1 September 2027 for SMEs and micro-companies, as the factory's
docblock says); then the application changes the gateway's factory, not its code.

## Installation

```sh
composer require omnibill/email
```

PHP 8.2 or later, `omnisense/omnibill` and `symfony/mailer` (a library: any transport it knows -
SMTP, an API's DSN).

```php
use Omnibill\Email\EmailGatewayFactory;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

$gateway = (new EmailGatewayFactory(new Mailer(Transport::fromDsn('smtp://localhost:25')), $statusStore))
    ->create(['from' => 'Atelier Tilleul <factures@atelier.example>']);
```

## Credentials

None of its own: the mailer's transport (an SMTP server, an e-mail API's DSN) is the application's,
configured with Symfony Mailer.

## Options

| Option | Default | |
|---|---|---|
| `from` | required | the sender: an address, or `Name <address>` |
| `reply_to` | | where the buyer's answers go |
| `bcc` | `[]` | a copy of each invoice sent: a list, or one comma-separated string |
| `subject` | `Facture {number}` | `{number}`, `{seller}`, `{buyer}`, `{total}`, `{currency}` |
| `body` | "Bonjour, Veuillez trouver ci-joint la facture {number}. Cordialement, {seller}" | the text, the same placeholders |

## Capabilities

| Request | Supported |
|---|---|
| `Submit` | yes: Factur-X, UBL, CII, attached |
| `FetchStatus` | yes: the statuses recorded |
| `SetStatus` | yes: recorded, nothing sent |
| `Receive`, `Lookup`, `Report`, `Notify` | no: `NotSupportedException` |

`capabilities()`: Factur-X, UBL, CII; not an approved platform; not public sector; no size limit.

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

## Behaviour

- The e-mail: from `from`, to the buyer's e-mail with the buyer's name, `reply_to` and every `bcc`
  when set, the subject (whitespace runs made one space) and the text body from the templates, the
  file attached under its file name and its syntax's mime type. No HTML part.
- The reference is a random `<32 hexadecimal characters>@omnibill`, set as the e-mail's
  `Message-ID`: a reply or a bounce can be matched to the flow.
- The flow: `ACCEPTED` (the mailer took it), kind invoice, direction out, status `SENT` with one
  history entry, `submittedAt` and `updatedAt` now, `name` the file name, `data['to']` the address.
- `setStatus()` needs the flow (`InvalidConfigException` without), fills the change's invoice number
  from the flow and its time with now when they are missing, records it, and answers the flow with
  the whole history.

## Example

The first script of [the manual's installation](https://github.com/glitchr-omnisense/omnibill/blob/1.x/docs/installation.md#plain-php)
is this gateway's: an invoice sent through `null://null`, then paid. Run on 2026-10-26 (PHP 8.4):

```
$ php bill.php
F-2026-042: Envoyée to compta@erable.example, 0d878a0b862f4c1814a2e02c45368700@omnibill
  2026-10-26 09:34 Envoyée
  2026-10-26 09:34 Encaissée (212)
```

In Symfony, with the application's mailer and a store of its own (autowired):

```yaml
omnibill:
    gateways:
        invoices:
            factory: email
            options:
                from: 'Atelier Tilleul <factures@atelier.example>'
                bcc: '%env(default::INVOICE_ARCHIVE)%'
```

## Errors

| Case | Exception |
|---|---|
| no mailer given to the factory | `InvalidConfigException`, when the gateway is built |
| `from` missing | `InvalidConfigException` (`The "email" gateway needs: from.`) |
| the invoice without a buyer, or the buyer without an e-mail | `InvalidConfigException`, nothing sent |
| `setStatus()` without the flow | `InvalidConfigException` |
| the mailer's transport fails | `UnreachableException` (`The mailer could not send the invoice: ...`), nothing recorded |

## Verified, and not

| | |
|---|---|
| An invoice sent for real | **done on 2026-10-08**: the harness's `bare --live` through a local SMTP server (Mailpit, in a throwaway container) - received from `Atelier Tilleul <factures@atelier.example>`, to `compta@erable.example`, subject "Facture F-2026-042", the PDF attached (`application/pdf`), the Message-ID the flow's reference |
| The message, the statuses, the errors | by the tests, on an in-memory mailer |
| Delivery to a real mailbox (SPF, DKIM, spam filters) | not this package's: the application's mailer |

The package's tests (`Tests/EmailGatewayTest.php`, 4 tests) pass on 2026-10-26 (PHP 8.4).

## Responsibility

This package sends e-mails through the application's own mailer and reaches no third-party service
of its own. Whoever runs it is responsible for the invoices it sends and for the obligations of
electronic invoicing that apply to them. The MIT licence covers this package's code only.
