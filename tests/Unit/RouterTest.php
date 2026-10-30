<?php

declare(strict_types=1);

namespace Isis\Tests\Unit;

use Isis\Core\HttpException;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Router;
use Isis\Services\Installer;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testParametrosNomeadosEPadroes(): void
    {
        $router = new Router();
        $router->get('/usuarios/{id}/editar', [self::class, 'x']);
        $router->get('/convite/{token}', [self::class, 'x']);

        self::assertSame(['id' => '42'], $router->match('GET', '/usuarios/42/editar')['params']);
        self::assertNull($router->match('GET', '/usuarios/abc/editar'));
        self::assertNull($router->match('POST', '/usuarios/42/editar'));
        self::assertNotNull($router->match('GET', '/convite/' . str_repeat('a', 43)));
        self::assertNull($router->match('GET', '/convite/curto'));
        self::assertNull($router->match('GET', "/convite/'or'1'='1aaaaaaaaaaaaaaaaaaaa"));
    }

    public function testPostSemTokenCsrfERecusado(): void
    {
        $router = new Router();
        $router->post('/acao', [self::class, 'x']);
        try {
            $router->dispatch(new Request('POST', '/acao', [], ['_token' => 'falso']));
            self::fail('Deveria recusar o POST sem token válido.');
        } catch (HttpException $e) {
            self::assertSame(419, $e->status);
        }
    }

    public function testRotaInexistenteResponde404(): void
    {
        $this->expectException(HttpException::class);
        (new Router())->dispatch(new Request('GET', '/nao-existe'));
    }

    public function testRedirecionamentoSoParaCaminhoInterno(): void
    {
        self::assertSame('/painel', Response::redirect('/painel')->headers['Location']);
        self::assertSame('/', Response::redirect('https://malicioso.example')->headers['Location']);
        self::assertSame('/', Response::redirect('//malicioso.example')->headers['Location']);
    }

    public function testDivisaoDeComandosSql(): void
    {
        $sql = "-- comentário\nCREATE TABLE a (id INT);\n\nCREATE TABLE b (\n  id INT\n);\n";
        self::assertSame(['CREATE TABLE a (id INT)', "CREATE TABLE b (\n  id INT\n)"], Installer::splitStatements($sql));
    }

    public function x(): Response
    {
        return new Response('ok');
    }
}
