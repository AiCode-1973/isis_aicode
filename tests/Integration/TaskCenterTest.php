<?php

declare(strict_types=1);

namespace Isis\Tests\Integration;

use Isis\Auth\Auth;
use Isis\Core\Database;
use Isis\Repositories\ActionRepository;
use Isis\Services\TaskCenterService;

/**
 * Central "O que preciso fazer?": junta as pendências da empresa certa, respeita o perfil,
 * ordena por prioridade (o prazo sobe a prioridade) e filtra.
 */
final class TaskCenterTest extends DatabaseTestCase
{
    private function action(string $title, ?int $ownerId, ?string $due, string $priority = 'baixa'): int
    {
        return (new ActionRepository())->create(['title' => $title, 'owner_user_id' => $ownerId, 'due_date' => $due, 'priority' => $priority, 'status' => 'pendente']);
    }

    private function find(array $tasks, string $title): ?array
    {
        foreach ($tasks as $t) {
            if ($t['title'] === $title) {
                return $t;
            }
        }
        return null;
    }

    public function testJuntaPendenciasDeVariosModulosSoDaEmpresaCerta(): void
    {
        [$a, $b] = $this->seedTwoCompanies();
        Auth::actAs($b['users']['admin'], $b['tenant']);
        $this->action('Tarefa só da loja', null, null);

        Auth::actAs($a['users']['admin'], $a['tenant']);
        $tasks = (new TaskCenterService())->tasks();
        self::assertNotEmpty($tasks);
        self::assertNull($this->find($tasks, 'Tarefa só da loja'), 'Nunca mostra tarefas de outra empresa.');
        self::assertGreaterThanOrEqual(3, count(array_unique(array_column($tasks, 'module'))));

        // Todo link aponta para um registro da própria empresa.
        $tables = ['acoes' => 'actions', 'titulares' => 'dsar_requests', 'incidentes' => 'incidents', 'riscos' => 'risks', 'inventario' => 'processing_activities',
            'ripd' => 'dpias', 'fornecedores' => 'vendors', 'politicas' => 'policies', 'treinamentos' => 'training_modules'];
        foreach ($tasks as $t) {
            $id = (int) substr($t['link'], strrpos($t['link'], '/') + 1);
            self::assertSame((int) $a['tenant']['id'], (int) Database::value("SELECT tenant_id FROM {$tables[$t['module']]} WHERE id = ?", [$id]), $t['link']);
            self::assertNotSame('', $t['next'], 'Toda tarefa tem um próximo passo.');
        }

        // Ordem: prioridade desc e, dentro dela, prazo mais próximo primeiro (sem prazo por último).
        for ($i = 1; $i < count($tasks); $i++) {
            self::assertGreaterThanOrEqual($tasks[$i]['priority'], $tasks[$i - 1]['priority']);
        }
    }

    public function testPrazoSobeAPrioridade(): void
    {
        [$a] = $this->seedTwoCompanies();
        Auth::actAs($a['users']['admin'], $a['tenant']);
        $owner = (int) $a['users']['gestor']['id'];
        $this->action('Vencida', $owner, date('Y-m-d', strtotime('-2 days')));
        $this->action('Amanhã', $owner, date('Y-m-d', strtotime('+1 day')));
        $this->action('Daqui a 10 dias', $owner, date('Y-m-d', strtotime('+10 days')));
        $this->action('Sem prazo', null, null);

        $center = new TaskCenterService();
        $tasks = $center->tasks();
        self::assertSame(4, $this->find($tasks, 'Vencida')['priority']);
        self::assertSame(3, $this->find($tasks, 'Amanhã')['priority']);
        self::assertSame(2, $this->find($tasks, 'Daqui a 10 dias')['priority']);
        self::assertSame(1, $this->find($tasks, 'Sem prazo')['priority']);
        self::assertSame(-2, $center->daysLeft($this->find($tasks, 'Vencida')['due']));
        self::assertStringContainsString('responsável', $this->find($tasks, 'Sem prazo')['next']);

        $overdue = $center->filter($tasks, ['prazo' => 'vencidas']);
        self::assertNotNull($this->find($overdue, 'Vencida'));
        self::assertNull($this->find($overdue, 'Amanhã'));
        self::assertNull($this->find($center->filter($tasks, ['escopo' => 'sem_responsavel']), 'Amanhã'));
        self::assertNotNull($this->find($center->filter($tasks, ['modulo' => 'acoes', 'prioridade' => '3']), 'Amanhã'));
        self::assertSame([], $center->filter($tasks, ['modulo' => 'acoes', 'prioridade' => '3', 'escopo' => 'minhas']), 'A administradora não é responsável por elas.');
    }

    public function testColaboradorSoVeOQueEDele(): void
    {
        [$a] = $this->seedTwoCompanies();
        Auth::actAs($a['users']['admin'], $a['tenant']);
        $collaborator = (int) $a['users']['colaborador']['id'];
        $this->action('Do colaborador', $collaborator, date('Y-m-d', strtotime('+2 days')));
        $this->action('De outra pessoa', (int) $a['users']['gestor']['id'], date('Y-m-d', strtotime('+2 days')));

        Auth::actAs($a['users']['colaborador'], $a['tenant']);
        $center = new TaskCenterService();
        $tasks = $center->tasks();
        self::assertNotNull($this->find($tasks, 'Do colaborador'));
        self::assertNull($this->find($tasks, 'De outra pessoa'));
        $modules = array_unique(array_column($tasks, 'module'));
        self::assertSame([], array_diff($modules, ['acoes', 'politicas', 'treinamentos']), 'Sem riscos, incidentes, titulares ou inventário.');
        foreach ($tasks as $t) {
            self::assertTrue($t['mine'], "{$t['title']} deveria ser do colaborador.");
        }
        self::assertSame(count($tasks), $center->summary($tasks)['mine']);
    }
}
