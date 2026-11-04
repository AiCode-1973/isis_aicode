<?php

declare(strict_types=1);

namespace Isis\Tests\Unit;

use Isis\Core\View;
use Isis\Support\Help;
use PHPUnit\Framework\TestCase;

/**
 * Ajuda dentro das telas: cada endereço leva à ajuda certa, todo conteúdo está completo
 * e "obrigatório" bate com as regras de validação dos controllers.
 */
final class HelpTest extends TestCase
{
    /** Formulário da ajuda => controller que valida o envio. */
    private const array FORMS = [
        'inventario.form' => 'InventoryController',
        'riscos.form' => 'RiskController',
        'ripd.form' => 'DpiaController',
        'planos.form' => 'ActionController',
        'titulares.form' => 'RequestController',
        'incidentes.form' => 'IncidentController',
        'fornecedores.form' => 'VendorController',
        'politicas.form' => 'PolicyController',
        'treinamentos.form' => 'TrainingController',
        'usuarios' => 'UserController',
        'usuarios.form' => 'UserController',
        'configuracoes' => 'SettingsController',
        'perfil' => 'ProfileController',
    ];

    public function testEnderecosLevamAAjudaCerta(): void
    {
        $cases = [
            '/painel' => 'painel', '/tarefas' => 'tarefas', '/inventario' => 'inventario', '/inventario/catalogos' => 'catalogos',
            '/inventario/nova' => 'inventario.form', '/inventario/12/editar' => 'inventario.form', '/inventario/12' => 'inventario.show',
            '/riscos/novo' => 'riscos.form', '/planos/nova' => 'planos.form', '/planos/3' => 'planos.show', '/titulares/nova' => 'titulares.form',
            '/incidentes/novo' => 'incidentes.form', '/incidentes/7' => 'incidentes.show', '/politicas/nova' => 'politicas.form',
            '/usuarios/4/editar' => 'usuarios.form', '/perfil/' => 'perfil',
        ];
        foreach ($cases as $path => $key) {
            self::assertSame($key, Help::forPath($path)['key'] ?? null, $path);
        }
        self::assertNull(Help::forPath('/manual'));
        self::assertNull(Help::forPath('/inventario/exportar'));
    }

    public function testConteudoCompleto(): void
    {
        foreach (Help::keys() as $key) {
            $help = Help::get($key);
            self::assertNotSame('', trim($help['title']), $key);
            self::assertGreaterThan(40, mb_strlen($help['intro']), "{$key}: a introdução explica a tela.");
            foreach ($help['fields'] as $name => $field) {
                self::assertCount(4, $field, "{$key}.{$name}");
                self::assertIsBool($field[1], "{$key}.{$name}: obrigatório deve ser sim ou não.");
                self::assertTrue($field[2] !== null || $field[3] !== null, "{$key}.{$name}: precisa de explicação ou exemplo.");
            }
        }
    }

    public function testObrigatorioBateComAsRegrasDeValidacao(): void
    {
        foreach (self::FORMS as $key => $controller) {
            $source = (string) file_get_contents(dirname(__DIR__, 2) . "/app/Controllers/{$controller}.php");
            preg_match_all("/'([a-z_]+)' => 'required/", $source, $m);
            $requiredInCode = array_unique($m[1]);
            foreach (Help::get($key)['fields'] as $name => [$label, $required]) {
                $field = rtrim($name, '[]');
                if ($required) {
                    self::assertContains($field, $requiredInCode, "{$key}: \"{$label}\" está como obrigatório na ajuda, mas não no {$controller}.");
                } else {
                    self::assertNotContains($field, $requiredInCode, "{$key}: \"{$label}\" está como opcional na ajuda, mas é obrigatório no {$controller}.");
                }
            }
        }
    }

    public function testVariavelContentDaTelaNaoSubstituiAPaginaNoLayout(): void
    {
        $html = View::render('partials/disclaimer', ['title' => 'Teste', 'content' => '<b>TEXTO-DA-TELA</b>'], 'onboarding');
        self::assertStringNotContainsString('TEXTO-DA-TELA', $html);
        self::assertStringContainsString('não substitui análise jurídica', $html);
    }
}
