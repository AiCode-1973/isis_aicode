<?php

declare(strict_types=1);

namespace Isis\Tests\Unit;

use Isis\Core\Crypto;
use PHPUnit\Framework\TestCase;

final class CryptoTest extends TestCase
{
    public function testCifraEDecifraNoMesmoContexto(): void
    {
        $cipher = Crypto::encrypt('123.456.789-00', 'tenant:1');
        self::assertNotNull($cipher);
        self::assertStringStartsWith('v1:', $cipher);
        self::assertStringNotContainsString('123.456', $cipher);
        self::assertSame('123.456.789-00', Crypto::decrypt($cipher, 'tenant:1'));
    }

    public function testCadaCifraUsaNonceDiferente(): void
    {
        self::assertNotSame(Crypto::encrypt('mesmo valor', 'x'), Crypto::encrypt('mesmo valor', 'x'));
    }

    public function testDadoDeOutraEmpresaNaoDecifra(): void
    {
        $cipher = Crypto::encrypt('dado sensível', 'tenant:1');
        $this->expectException(\RuntimeException::class);
        Crypto::decrypt($cipher, 'tenant:2');
    }

    public function testAdulteracaoEDetectada(): void
    {
        $cipher = (string) Crypto::encrypt('dado sensível', 'tenant:1');
        $raw = base64_decode(substr($cipher, 3));
        $raw[30] = chr(ord($raw[30]) ^ 1);
        $this->expectException(\RuntimeException::class);
        Crypto::decrypt('v1:' . base64_encode($raw), 'tenant:1');
    }

    public function testIndiceCegoEDeterministicoEIgnoraCaixa(): void
    {
        self::assertSame(Crypto::blindIndex('Ana@Ex.com ', 'email'), Crypto::blindIndex('ana@ex.com', 'email'));
        self::assertNotSame(Crypto::blindIndex('ana@ex.com', 'email'), Crypto::blindIndex('ana@ex.com', 'outro'));
        self::assertSame(64, strlen(Crypto::blindIndex('x', 'y')));
    }

    public function testTokensAleatoriosSeguros(): void
    {
        $token = Crypto::randomToken();
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token);
        self::assertNotSame($token, Crypto::randomToken());
        self::assertSame(hash('sha256', $token), Crypto::hashToken($token));
    }

    public function testChaveInvalidaERecusada(): void
    {
        $this->expectException(\RuntimeException::class);
        Crypto::setKey('base64:' . base64_encode('curta'));
    }
}
