<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Core\Database;
use Isis\Core\View;
use Isis\Repositories\PlatformRepository;
use Isis\Services\AccountService;
use Isis\Support\LegalDocs;

/**
 * Termos de uso, aviso de privacidade e acordo de tratamento: identificação da AiCode e registro do aceite.
 */
final class LegalDocsTest extends DatabaseTestCase
{
    public function testSemIdentificacaoAsPaginasMostramOQueFalta(): void
    {
        self::assertContains('Razão social da AiCode', LegalDocs::missing());
        self::assertNotContains('Suboperadores', LegalDocs::missing(), 'A lista de suboperadores é opcional.');
        $html = View::render('legal/privacy', ['title' => 'Aviso de privacidade', 'info' => LegalDocs::info()], null);
        self::assertStringContainsString(LegalDocs::PENDING, $html);
    }

    public function testIdentificacaoApareceNosTresDocumentos(): void
    {
        $repo = new PlatformRepository();
        $repo->saveSetting('legal_name', 'AiCode Tecnologia Ltda.');
        $repo->saveSetting('legal_cnpj', '12.345.678/0001-95');
        $repo->saveSetting('legal_address', 'Rua Exemplo, 100, São Paulo/SP');
        $repo->saveSetting('privacy_email', 'privacidade@aicode.example');
        $repo->saveSetting('dpo_name', 'Encarregado Exemplo');
        $repo->saveSetting('subprocessors', "Hospedagem X · servidor · Brasil\nE-mail Y · envio de e-mails · Brasil");
        self::assertSame([], LegalDocs::missing());

        $info = LegalDocs::info();
        $privacy = View::render('legal/privacy', ['info' => $info], null);
        self::assertStringContainsString('AiCode Tecnologia Ltda.', $privacy);
        self::assertStringContainsString('privacidade@aicode.example', $privacy);
        self::assertStringNotContainsString(LegalDocs::PENDING, $privacy);

        $dpa = View::render('legal/dpa', ['info' => $info], null);
        self::assertStringContainsString('<li>E-mail Y · envio de e-mails · Brasil</li>', $dpa);
        self::assertStringContainsString('Encarregado Exemplo', $dpa);

        self::assertStringContainsString('AiCode Tecnologia Ltda.', View::render('home/terms', ['info' => $info], null));
    }

    public function testCadastroRegistraAVersaoDosDocumentosAceitos(): void
    {
        AccountService::registerCompany([
            'legal_name' => 'Escola Saber Mais Ltda.',
            'trade_name' => 'Escola Saber Mais',
            'cnpj' => '11222333000181',
            'segment_code' => 'educacao',
            'name' => 'Paula Reis',
            'email' => 'paula@sabermais.example',
            'password' => 'Senha-Forte-2026',
        ]);
        $changes = (string) Database::value("SELECT changes FROM audit_logs WHERE action = 'tenant.registered' ORDER BY id DESC LIMIT 1");
        self::assertStringContainsString('Acordo de tratamento de dados', $changes);
        self::assertStringContainsString(LegalDocs::VERSION, str_replace('\\/', '/', $changes));
    }
}
