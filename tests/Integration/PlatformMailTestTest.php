<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Auth\PlatformAuth;
use Isis\Controllers\PlatformController;
use Isis\Core\Config;
use Isis\Core\Database;
use Isis\Core\Mailer;
use Isis\Core\Request;
use Isis\Repositories\PlatformRepository;

/**
 * Teste de e-mail pelo painel da plataforma (hospedagem sem terminal): envia, registra o resultado
 * para a tela, fica na auditoria, limita repetições e nunca mostra a senha.
 */
final class PlatformMailTestTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        Config::set('mail.driver', 'array');
        Config::set('mail.password', null);
        Config::set('mail.host', null);
        PlatformAuth::actAs(null);
        parent::tearDown();
    }

    private function actAsAdmin(): void
    {
        $repo = new PlatformRepository();
        $id = $repo->saveAdmin('Equipe AiCode', 'equipe@aicode.example', Auth::hashPassword('Plataforma@2026x'));
        PlatformAuth::actAs($repo->findAdmin($id));
    }

    private function test(string $to): \Isis\Core\Response
    {
        return (new PlatformController())->mailTest(new Request('POST', '/plataforma/email-teste', [], ['to' => $to]));
    }

    public function testEnviaRegistraResultadoEAuditoria(): void
    {
        $this->actAsAdmin();
        $response = $this->test('destino@aicode.example');
        self::assertSame('/plataforma/configuracoes#email', $response->headers['Location']);
        self::assertSame('destino@aicode.example', end(Mailer::$sent)['to']);
        $result = $_SESSION['_flash']['mail_test'];
        self::assertTrue($result['sent']);
        self::assertSame('array', $result['driver']);
        self::assertSame([], $result['steps'], 'Sem SMTP não há diagnóstico de conexão.');
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM audit_logs WHERE action = 'platform.mail_test'"));
    }

    public function testDestinoInvalidoELimiteDeTentativas(): void
    {
        $this->actAsAdmin();
        $this->test('nao-e-email');
        self::assertArrayHasKey('to', $_SESSION['_flash']['_errors']);
        self::assertSame([], Mailer::$sent);

        for ($i = 0; $i < 10; $i++) {
            $this->test('destino@aicode.example');
        }
        Mailer::$sent = [];
        $this->test('destino@aicode.example');
        self::assertSame([], Mailer::$sent, 'Depois de 10 testes em 15 minutos, bloqueia.');
        self::assertStringContainsString('Muitos testes', $_SESSION['_flash']['error']);
    }

    public function testTelaMostraConfiguracaoSemASenha(): void
    {
        $this->actAsAdmin();
        Config::set('mail.driver', 'smtp');
        Config::set('mail.host', 'smtp.exemplo.invalid');
        Config::set('mail.password', 'SenhaSecreta-123');
        $html = (new PlatformController())->settings(new Request('GET', '/plataforma/configuracoes'))->body;
        self::assertStringContainsString('smtp.exemplo.invalid', $html);
        self::assertStringContainsString('senha preenchida', $html);
        self::assertStringNotContainsString('SenhaSecreta-123', $html);
    }
}
