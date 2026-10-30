<?php

declare(strict_types=1);

namespace Isis\Tests\Unit;

use Isis\Core\Validator;
use Isis\Database\Seeds\DemoSeeder;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testCnpjNumericoValido(): void
    {
        self::assertTrue(Validator::isCnpj('11.222.333/0001-81'));
        self::assertFalse(Validator::isCnpj('11.222.333/0001-82'));
        self::assertFalse(Validator::isCnpj('11.111.111/1111-11'));
        self::assertFalse(Validator::isCnpj('123'));
    }

    public function testCnpjAlfanumericoSegueExemploOficialDaReceita(): void
    {
        // Exemplo publicado pela Receita Federal para o CNPJ alfanumérico: 12.ABC.345/01DE-35
        self::assertSame('12ABC34501DE35', DemoSeeder::cnpj('12ABC34501DE'));
        self::assertTrue(Validator::isCnpj('12.ABC.345/01DE-35'));
        self::assertTrue(Validator::isCnpj('12abc34501de35'));
        self::assertFalse(Validator::isCnpj('12.ABC.345/01DE-36'));
    }

    public function testNormalizaEmailECnpj(): void
    {
        $v = Validator::make(
            ['email' => '  Fulano@Empresa.COM.br ', 'cnpj' => '11.222.333/0001-81'],
            ['email' => 'required|email', 'cnpj' => 'required|cnpj'],
        );
        self::assertFalse($v->fails());
        self::assertSame('fulano@empresa.com.br', $v->validated()['email']);
        self::assertSame('11222333000181', $v->validated()['cnpj']);
    }

    public function testPoliticaDeSenha(): void
    {
        self::assertNotNull(Validator::passwordProblem('curta1A'));
        self::assertNotNull(Validator::passwordProblem('semnumerosaqui'));
        self::assertNotNull(Validator::passwordProblem('sem maiuscula 123'));
        self::assertNull(Validator::passwordProblem('Senha-Forte-2026'));
    }

    public function testObrigatorioConfirmacaoEOpcoes(): void
    {
        $v = Validator::make(
            ['name' => '', 'password' => 'Senha-Forte-2026', 'password_confirmation' => 'outra', 'role' => 'root'],
            ['name' => 'required', 'password' => 'required|password', 'password_confirmation' => 'same:password', 'role' => 'in:admin,auditor', 'terms' => 'accepted'],
        );
        self::assertTrue($v->fails());
        self::assertSame(['name', 'password_confirmation', 'role', 'terms'], array_keys($v->errors()));
    }

    public function testRejeitaArrayOndeEsperaTexto(): void
    {
        $v = Validator::make(['name' => ['x']], ['name' => 'required|max:10']);
        self::assertTrue($v->fails());
    }
}
