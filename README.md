# omnibill/email

**The invoice by e-mail** for [omnisense/omnibill](https://github.com/glitchr-omnisense/omnibill): the
file attached to a message to the buyer, sent through the application's `symfony/mailer`, its
statuses - sent, paid, refused - kept by hand. No platform, no account: the default gateway, and
the one the tests use.

```php
use Omnibill\Email\EmailGatewayFactory;

$gateway = (new EmailGatewayFactory($mailer, $statusStore))->create(['from' => 'Atelier <factures@example.org>']);

$flow = $gateway->submit($invoice);              // to $invoice->buyer->email, the file attached: SENT
$gateway->setStatus(new StatusChange(LifecycleStatus::PAID, amount: '1250.00'), $flow);
$gateway->fetchStatus($flow)->history;           // SENT, then PAID - as recorded
```

```yaml
omnibill:
    gateways:
        invoices: { factory: email, options: { from: 'Atelier <factures@example.org>', bcc: archives@example.org } }
```

It is **not an approved platform**: a small company may still send its invoices this way until it
must issue through one (1 September 2027 for SMEs and micro-companies); receiving through an
approved platform is mandatory since 1 September 2026, and is not this gateway's. On that date the
site changes factory (`afnor`), not its code.

[Documentation](docs/index.md): the options, the message, the store, what was verified - an invoice
sent for real to a local SMTP server, on 2026-10-08.

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
