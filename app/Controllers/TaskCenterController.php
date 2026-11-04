<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Services\TaskCenterService;

/**
 * Central "O que preciso fazer?": tarefas pendentes de todos os módulos numa lista só, com filtros.
 */
final class TaskCenterController extends Controller
{
    public function index(Request $request): Response
    {
        $center = new TaskCenterService();
        $all = $center->tasks();
        $filters = [
            'escopo' => in_array($request->query('escopo'), ['minhas', 'sem_responsavel'], true) ? (string) $request->query('escopo') : '',
            'prioridade' => isset(TaskCenterService::PRIORITIES[(int) $request->query('prioridade', '0')]) ? (string) (int) $request->query('prioridade') : '',
            'modulo' => isset(TaskCenterService::MODULES[(string) $request->query('modulo', '')]) ? (string) $request->query('modulo') : '',
            'prazo' => in_array($request->query('prazo'), ['vencidas', '7', '30'], true) ? (string) $request->query('prazo') : '',
        ];
        $tasks = $center->filter($all, $filters);
        $modules = array_intersect_key(TaskCenterService::MODULES, array_flip(array_unique(array_column($all, 'module'))));

        return $this->view('tasks/index', [
            'title' => 'O que preciso fazer?',
            'tasks' => $tasks,
            'summary' => $center->summary($all),
            'filters' => $filters,
            'filtered' => array_filter($filters) !== [],
            'modules' => $modules,
            'center' => $center,
        ]);
    }
}
