<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Core\Database;
use Isis\Repositories\TenantRepository;
use Isis\Services\OnboardingService as Wizard;

/**
 * Assistente de configuração inicial: etapas na ordem, validação, plano sugerido pelas respostas
 * e conclusão (áreas, categorias, encarregado e convites só na empresa de quem configurou).
 */
final class OnboardingWizardTest extends DatabaseTestCase
{
    private function answer(string $step, array $input, array $answers): array
    {
        [$data, $errors] = Wizard::validate($step, $input, $answers);
        self::assertSame([], $errors, "Etapa {$step} deveria ser aceita.");
        $answers[$step] = $data;
        return $answers;
    }

    /** Respostas completas de uma clínica com dados sensíveis, fornecedores e dois convites. */
    private function clinicAnswers(): array
    {
        $a = $this->answer('empresa', [
            'segment_code' => 'saude', 'size' => '11_50', 'audiences' => ['pessoas'], 'channels' => ['site', 'invalido'], 'existing' => ['politicas'],
        ], []);
        $a = $this->answer('areas', ['departments' => ['Recepção e Atendimento', 'Faturamento e Convênios'], 'other_departments' => "Ouvidoria\n\nOuvidoria"], $a);
        $a = $this->answer('dados', [
            'data_categories' => ['Dados de saúde (prontuário, exames, diagnósticos)', 'Dados de contato', 'Inventado pelo navegador'],
            'other_data' => 'Fotos de procedimentos', 'subject_categories' => ['Pacientes', 'Responsáveis legais'],
            'vendors' => 'sim', 'international' => 'nao',
        ], $a);
        return $this->answer('responsaveis', [
            'dpo_name' => 'Rafaela Dias', 'dpo_email' => 'Rafaela@VidaPlena.example', 'privacy_email' => '', 'invite_dpo' => '1',
            'owner_name' => ['Júlia Prado', '', 'Otávio Lins'], 'owner_email' => ['julia@vidaplena.example', '', 'otavio@vidaplena.example'],
            'owner_invite' => ['1', '', ''],
        ], $a);
    }

    public function testRespostasSaoNormalizadas(): void
    {
        $a = $this->clinicAnswers();
        self::assertSame(['site'], $a['empresa']['channels'], 'Opções fora da lista são descartadas.');
        self::assertSame(['Recepção e Atendimento', 'Faturamento e Convênios', 'Ouvidoria'], $a['areas']['departments']);
        self::assertSame(['Dados de saúde (prontuário, exames, diagnósticos)', 'Dados de contato', 'Fotos de procedimentos'], $a['dados']['data_categories']);
        self::assertSame('rafaela@vidaplena.example', $a['responsaveis']['dpo_email']);
        self::assertCount(2, $a['responsaveis']['owners'], 'Áreas sem responsável ficam de fora.');
        self::assertSame('Ouvidoria', $a['responsaveis']['owners'][1]['department']);
        self::assertFalse($a['responsaveis']['owners'][1]['invite']);
    }

    public function testEtapasExigemRespostasEValidam(): void
    {
        [, $errors] = Wizard::validate('empresa', ['segment_code' => 'nao-existe', 'size' => ''], []);
        self::assertArrayHasKey('segment_code', $errors);
        self::assertArrayHasKey('size', $errors);
        self::assertArrayHasKey('audiences', $errors);

        [, $errors] = Wizard::validate('dados', ['vendors' => 'talvez'], ['empresa' => ['segment_code' => 'saude']]);
        self::assertSame(['data_categories', 'subject_categories', 'vendors', 'international'], array_keys($errors));

        [, $errors] = Wizard::validate('responsaveis', [
            'dpo_name' => 'Ana Reis', 'dpo_email' => 'ana@x.example', 'owner_name' => [''], 'owner_email' => ['nao-e-email'],
        ], ['areas' => ['departments' => ['Vendas']]]);
        self::assertArrayHasKey('owner_name_0', $errors);
        self::assertArrayHasKey('owner_email_0', $errors);
    }

    public function testNaoPulaEtapas(): void
    {
        self::assertSame('empresa', Wizard::nextStep([]));
        self::assertFalse(Wizard::canOpen('plano', []));
        self::assertFalse(Wizard::canOpen('dados', ['empresa' => []]));
        self::assertTrue(Wizard::canOpen('areas', ['empresa' => []]));
        self::assertSame('plano', Wizard::nextStep($this->clinicAnswers()));
    }

    public function testPlanoSegueAsRespostas(): void
    {
        $keys = array_column(Wizard::plan($this->clinicAnswers()), 'key');
        self::assertSame('inventario', $keys[0], 'O inventário vem primeiro quando ainda não existe mapeamento.');
        self::assertContains('ripd', $keys, 'Dados de saúde sugerem o relatório de impacto.');
        self::assertLessThan(array_search('politicas', $keys, true), array_search('riscos', $keys, true));
        self::assertLessThan(array_search('treinamentos', $keys, true), array_search('fornecedores', $keys, true));

        // Empresa pequena, só B2B, sem dados sensíveis e sem fornecedores
        $simple = $this->answer('empresa', ['segment_code' => 'servicos', 'size' => 'ate_10', 'audiences' => ['empresas'], 'existing' => ['inventario']], []);
        $simple = $this->answer('areas', ['departments' => ['Comercial']], $simple);
        $simple = $this->answer('dados', ['data_categories' => ['Dados de contato'], 'subject_categories' => ['Clientes'], 'vendors' => 'nao', 'international' => 'nao'], $simple);
        $simple = $this->answer('responsaveis', ['dpo_name' => 'Caio Moura', 'dpo_email' => 'caio@x.example'], $simple);
        $plan = Wizard::plan($simple);
        $keys = array_column($plan, 'key');
        self::assertNotContains('ripd', $keys);
        self::assertNotContains('fornecedores', $keys);
        self::assertSame('equipe', end($keys), 'Equipe pequena sem convites deixa "trazer a equipe" para o fim.');
        foreach ($plan as $item) {
            self::assertNotEmpty($item['reasons'], "{$item['key']} precisa explicar o motivo.");
        }
    }

    public function testConclusaoCriaTudoSoNaEmpresaCertaEEnviaConvitesMarcados(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        Database::execute('UPDATE tenants SET onboarding_completed_at = NULL, onboarding_answers = NULL WHERE id = ?', [$a['tenant']['id']]);
        Auth::actAs($a['users']['admin'], $a['tenant']);
        $bDepartments = (int) Database::value('SELECT COUNT(*) FROM departments WHERE tenant_id = ?', [$b['tenant']['id']]);

        $lastInvite = (int) Database::value('SELECT COALESCE(MAX(id), 0) FROM invitations');
        $lastLog = (int) Database::value('SELECT COALESCE(MAX(id), 0) FROM audit_logs');
        $answers = $this->clinicAnswers();
        $answers['pendente'] = true;
        Wizard::save($answers);
        self::assertSame([], Wizard::complete($answers));

        $tenant = (new TenantRepository())->current();
        self::assertNotNull($tenant['onboarding_completed_at']);
        self::assertSame('Rafaela Dias', $tenant['dpo_name']);
        self::assertSame('rafaela@vidaplena.example', $tenant['privacy_email'], 'Sem e-mail público, usa o do encarregado.');
        $saved = Wizard::answers($tenant);
        self::assertArrayNotHasKey('pendente', $saved);
        self::assertArrayHasKey('concluido_em', $saved);

        $tid = (int) $a['tenant']['id'];
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM departments WHERE tenant_id = ? AND name = 'Ouvidoria'", [$tid]));
        self::assertSame(1, (int) Database::value("SELECT is_sensitive FROM data_categories WHERE tenant_id = ? AND name = 'Dados de saúde (prontuário, exames, diagnósticos)'", [$tid]));
        self::assertSame(0, (int) Database::value("SELECT is_sensitive FROM data_categories WHERE tenant_id = ? AND name = 'Fotos de procedimentos'", [$tid]));
        self::assertSame($bDepartments, (int) Database::value('SELECT COUNT(*) FROM departments WHERE tenant_id = ?', [$b['tenant']['id']]));

        // Convites: encarregado e a responsável marcada; o responsável sem marcação não recebe.
        $invites = Database::fetchAll('SELECT email, role, department_id, tenant_id FROM invitations WHERE id > ? ORDER BY id', [$lastInvite]);
        self::assertSame(['rafaela@vidaplena.example', 'julia@vidaplena.example'], array_column($invites, 'email'));
        self::assertSame(['encarregado', 'gestor'], array_column($invites, 'role'));
        $reception = (int) Database::value("SELECT id FROM departments WHERE tenant_id = ? AND name = 'Recepção e Atendimento'", [$tid]);
        self::assertSame($reception, (int) $invites[1]['department_id']);
        self::assertSame([$tid, $tid], array_map('intval', array_column($invites, 'tenant_id')));
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM audit_logs WHERE action = 'onboarding.completed' AND tenant_id = ? AND id > ?", [$tid, $lastLog]));

        // Refazer não duplica áreas nem categorias, e convites repetidos viram aviso.
        $warnings = Wizard::complete($answers);
        self::assertCount(2, $warnings);
        self::assertSame(1, (int) Database::value("SELECT COUNT(*) FROM departments WHERE tenant_id = ? AND name = 'Ouvidoria'", [$tid]));
    }
}
