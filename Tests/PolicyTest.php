<?php

namespace Omnibill\Email\Tests;

use Omnibill\Exception\UsageLimitReachedException;
use Omnibill\Model\Invoice;
use Omnibill\Model\Party;
use Omnibill\Model\Syntax;
use Omnibill\Email\EmailGatewayFactory;
use Omnibill\Registry;
use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;

/** The e-mail gateway's usage policy: declared in policy.json, read by the gateway, enforced through the registry. */
final class PolicyTest extends TestCase
{
    public function testThePolicyIsDeclaredAndRead(): void
    {
        $declared = UsagePolicy::fromFile(__DIR__.'/../policy.json');
        $composer = json_decode((string) file_get_contents(__DIR__.'/../composer.json'), true);

        self::assertIsArray($composer);
        $extra = $composer['extra'] ?? null;
        self::assertIsArray($extra);
        self::assertSame(['policy' => 'policy.json'], $extra['omnisense'] ?? null);
        self::assertSame('email', $declared->provider);
        self::assertSame('2026-11-10', $declared->provenance->verifiedAt);
        self::assertSame($declared->toArray(), EmailGatewayFactory::usagePolicy()->toArray());
        self::assertNull($declared->rateLimits, 'the mail server\'s, not a provider\'s');
        self::assertFalse($declared->requirements->key);

        $registry = new Registry([new EmailGatewayFactory(new Outbox())], ['email' => ['factory' => 'email', 'options' => ['from' => 'factures@atelier.example']]]);
        self::assertSame($declared->toArray(), $registry->policy('email')->toArray());
    }

    public function testASitesSendingLimitIsCounted(): void
    {
        $registry = new Registry([new EmailGatewayFactory(new Outbox())], ['email' => ['factory' => 'email', 'options' => ['from' => 'factures@atelier.example'], 'policy' => ['rateLimits' => [['max' => 1, 'window' => 'hour', 'operations' => ['submit'], 'note' => 'the mail server\'s']]]]]);
        $registry->get('email')->submit(self::invoice('F-1'));

        $this->expectException(UsageLimitReachedException::class);
        $registry->get('email')->submit(self::invoice('F-2'));
    }

    private static function invoice(string $number): Invoice
    {
        return new Invoice('%PDF-1.7 Factur-X', $number.'.pdf', Syntax::FACTURX, $number, new Party('Atelier Tilleul', '123456789'), new Party('Maison Érable', '987654321', email: 'compta@erable.example'), total: '10.00');
    }
}
