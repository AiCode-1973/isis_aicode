<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Controllers\IncidentController;
use Isis\Controllers\InventoryController;
use Isis\Controllers\ModelController;
use Isis\Controllers\RiskController;
use Isis\Core\Database;
use Isis\Core\Request;
use Isis\Support\Labels;
use Isis\Support\ModelLibrary;

/**
 * Modelos prontos: conteúdo válido para os formulários, preenchimento sem gravar nada
 * e criação de pacotes de ações só na empresa de quem usou o modelo.
 */
final class ModelLibraryTest extends DatabaseTestCase
{
    private const array PLACEHOLDERS = ['nome', 'protocolo', 'empresa', 'encarregado', 'contato', 'data_pedido'];

    public function testModelosUsamValoresAceitosPelosFormularios(): void
    {
        $bases = array_column(require dirname(__DIR__, 2) . '/database/seeds/legal_bases.php', 0);
        $segments = array_column(require dirname(__DIR__, 2) . '/database/seeds/segments.php', 'code');
        $keys = [];
        foreach (ModelLibrary::all() as $m) {
            $id = $m['type'] . ':' . $m['key'];
            self::assertArrayNotHasKey($id, $keys, "Modelo repetido: {$id}");
            $keys[$id] = true;
            self::assertArrayHasKey($m['type'], ModelLibrary::TYPES);
            self::assertNotSame('', trim($m['title']), $id);
            self::assertGreaterThan(20, mb_strlen($m['situation']), "{$id}: explique quando usar.");
            self::assertSame([], array_diff($m['segments'], $segments), "{$id}: segmento inexistente.");
            $f = $m['fields'] ?? [];
            match ($m['type']) {
                'inventario' => (function () use ($f, $id, $bases): void {
                    self::assertGreaterThan(0, mb_strlen($f['name']));
                    self::assertGreaterThan(0, mb_strlen($f['purpose']));
                    self::assertArrayHasKey($f['controller_role'], Labels::CONTROLLER_ROLE, $id);
                    self::assertContains($f['legal_basis_code'], $bases, $id);
                    if (isset($f['sensitive_legal_basis_code'])) {
                        self::assertStringStartsWith('art11', $f['sensitive_legal_basis_code'], $id);
                    }
                    self::assertArrayHasKey($f['data_source'], Labels::DATA_SOURCE, $id);
                })(),
                'risco' => (function () use ($f, $m, $id): void {
                    self::assertArrayHasKey($f['category'], Labels::RISK_CATEGORY, $id);
                    self::assertArrayHasKey($f['treatment'], Labels::RISK_TREATMENT, $id);
                    foreach (['likelihood', 'impact'] as $k) {
                        self::assertContains((int) $f[$k], [1, 2, 3, 4, 5], $id);
                    }
                    if (isset($m['plan'])) {
                        self::assertNotNull(ModelLibrary::find('plano', $m['plan']), "{$id}: plano sugerido inexistente.");
                    }
                })(),
                'plano' => (function () use ($m, $id): void {
                    self::assertGreaterThanOrEqual(2, count($m['actions']), $id);
                    foreach ($m['actions'] as [$title, , $priority, $days]) {
                        self::assertGreaterThanOrEqual(3, mb_strlen($title));
                        self::assertArrayHasKey($priority, Labels::PRIORITY, $id);
                        self::assertGreaterThan(0, $days);
                    }
                })(),
                'resposta' => (function () use ($m, $id): void {
                    self::assertContains($m['final_status'], ['respondida', 'indeferida'], $id);
                    self::assertSame([], array_diff($m['rights'] ?? [], array_keys(Labels::RIGHT_TYPE)), $id);
                    self::assertStringContainsString('{nome}', $m['text'], $id);
                    self::assertStringContainsString('{protocolo}', $m['text'], $id);
                    preg_match_all('/\{([a-z_]+)\}/', $m['text'], $found);
                    self::assertSame([], array_diff($found[1], self::PLACEHOLDERS), "{$id}: marca desconhecida.");
                })(),
                'incidente' => (function () use ($f, $id): void {
                    self::assertArrayHasKey($f['category'], Labels::INCIDENT_CATEGORY, $id);
                    self::assertContains($f['personal_data'], ['sim', 'nao', 'nao_sei'], $id);
                    self::assertGreaterThanOrEqual(5, mb_strlen($f['title']));
                    self::assertGreaterThanOrEqual(10, mb_strlen($f['description']));
                })(),
            };
        }
        foreach (array_keys(ModelLibrary::TYPES) as $type) {
            self::assertGreaterThanOrEqual(5, count(ModelLibrary::ofType($type)), "Poucos modelos de {$type}.");
        }
    }

    public function testInventarioFiltraPorSegmentoEAjudantes(): void
    {
        $saude = array_column(ModelLibrary::ofType('inventario', 'saude'), 'key');
        self::assertContains('prontuario', $saude);
        self::assertContains('folha-pagamento', $saude);
        self::assertNotContains('matricula-alunos', $saude);
        self::assertLessThan(array_search('folha-pagamento', $saude, true), array_search('prontuario', $saude, true), 'Os do segmento vêm primeiro.');

        self::assertSame('Olá, Ana. Pedido X-1.', ModelLibrary::fill('Olá, {nome}. Pedido {protocolo}.', ['nome' => 'Ana', 'protocolo' => 'X-1']));
        [$found, $missing] = ModelLibrary::matchCatalog([7 => ['name' => 'Dados de saúde (laudos)'], 9 => ['name' => 'Dados de contato']], ['DADOS DE CONTATO', 'Dados de saúde', 'Biometria']);
        self::assertSame([['id' => 9], ['id' => 7]], $found);
        self::assertSame(['Biometria'], $missing);
    }

    public function testModeloPreencheOFormularioSemGravar(): void
    {
        [$a] = $this->seedTwoCompanies();
        Auth::actAs($a['users']['admin'], $a['tenant']);
        $before = (int) Database::value('SELECT COUNT(*) FROM processing_activities');

        $html = (new InventoryController())->create(new Request('GET', '/inventario/nova', ['modelo' => 'folha-pagamento']))->body;
        self::assertStringContainsString('Modelo aplicado: Folha de pagamento', $html);
        self::assertStringContainsString('value="Gestão de colaboradores e folha de pagamento"', $html);
        self::assertSame($before, (int) Database::value('SELECT COUNT(*) FROM processing_activities'), 'Escolher um modelo não grava nada.');

        $html = (new RiskController())->create(new Request('GET', '/riscos/novo', ['modelo' => 'phishing']))->body;
        self::assertStringContainsString('Captura de senhas e dados por phishing', $html);
        self::assertStringContainsString('Conscientização da equipe', $html, 'Indica o plano sugerido.');

        $html = (new IncidentController())->create(new Request('GET', '/incidentes/novo', ['modelo' => 'perda-furto']))->body;
        self::assertStringContainsString('Perda ou furto de equipamento com dados pessoais', $html);

        $html = (new InventoryController())->create(new Request('GET', '/inventario/nova', ['modelo' => 'nao-existe']))->body;
        self::assertStringContainsString('Começar de um modelo', $html, 'Modelo desconhecido: formulário em branco com o seletor.');
    }

    public function testPacoteDeAcoesCriaSoAsMarcadasNaEmpresaCerta(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        Auth::actAs($a['users']['admin'], $a['tenant']);
        $risk = (int) Database::value("SELECT id FROM risks WHERE tenant_id = ? AND status IN ('identificado','em_tratamento') LIMIT 1", [$a['tenant']['id']]);
        $owner = (int) $a['users']['gestor']['id'];
        $before = (int) Database::value('SELECT COUNT(*) FROM actions');

        $response = (new ModelController())->createPlan(new Request('POST', '/planos/modelo/backup', [], [
            'risk_id' => (string) $risk,
            'include' => ['0' => '1', '2' => '1'],
            'action_title' => ['Backup diário configurado', 'Cópia fora', 'Teste de restauração', 'Atualizar'],
            'action_priority' => ['alta', 'alta', 'media', 'media'],
            'action_due' => ['2026-12-01', '', '2026-12-20', ''],
            'action_owner' => [(string) $owner, '', '', ''],
        ]), 'backup');
        self::assertSame(303, $response->status);

        $rows = Database::fetchAll('SELECT tenant_id, title, priority, due_date, owner_user_id, risk_id, status FROM actions WHERE id > (SELECT MAX(id) FROM actions) - 2 ORDER BY id');
        self::assertSame($before + 2, (int) Database::value('SELECT COUNT(*) FROM actions'), 'Só as duas marcadas.');
        self::assertSame(['Backup diário configurado', 'Teste de restauração'], array_column($rows, 'title'));
        self::assertSame([(int) $a['tenant']['id'], (int) $a['tenant']['id']], array_map('intval', array_column($rows, 'tenant_id')));
        self::assertSame([$risk, $risk], array_map('intval', array_column($rows, 'risk_id')));
        self::assertSame($owner, (int) $rows[0]['owner_user_id']);
        self::assertNull($rows[1]['owner_user_id']);
        self::assertSame('pendente', $rows[0]['status']);

        // Risco de outra empresa não é aceito.
        $other = (int) Database::value('SELECT id FROM risks WHERE tenant_id = ? LIMIT 1', [$b['tenant']['id']]);
        $count = (int) Database::value('SELECT COUNT(*) FROM actions');
        (new ModelController())->createPlan(new Request('POST', '/planos/modelo/backup', [], [
            'risk_id' => (string) $other, 'include' => ['0' => '1'], 'action_title' => ['Teste'], 'action_priority' => ['alta'],
        ]), 'backup');
        self::assertSame($count, (int) Database::value('SELECT COUNT(*) FROM actions'));
    }
}
