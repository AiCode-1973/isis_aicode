<?php

declare(strict_types=1);

namespace Isis\Controllers;

use Isis\Services\SubscriptionService;
use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\Permission as P;
use Isis\Core\HttpException;
use Isis\Core\Request;
use Isis\Core\Response;
use Isis\Core\Validator;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\TenantRepository;
use Isis\Repositories\TrainingRepository;
use Isis\Repositories\UserRepository;
use Isis\Services\Templates;
use Isis\Services\TrainingService;
use Isis\Support\Labels;

/**
 * Treinamentos: módulos com conteúdo e avaliação, designação, progresso e certificados verificáveis.
 */
final class TrainingController extends Controller
{
    private const int QUESTION_SLOTS = 10;

    public function index(Request $request): Response
    {
        $repo = new TrainingRepository();
        $manager = Auth::can(P::TRAININGS_MANAGE);
        return $this->view('trainings/index', [
            'title' => $manager ? 'Treinamentos' : 'Meus treinamentos',
            'mine' => $repo->forUser((int) Auth::id()),
            'modules' => $manager ? $repo->modules() : [],
            'stats' => $manager ? $repo->stats() : null,
            'manager' => $manager,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(['id' => null, 'duration_minutes' => 20, 'passing_score' => 70, 'certificate_months' => 12, 'content' => ''], [], 'Novo treinamento');
    }

    public function store(Request $request): Response
    {
        if (($limit = SubscriptionService::limitReached('trainings')) !== null) {
            return $this->failure('/treinamentos', $limit);
        }
        [$data, $questions, $errors] = $this->parse($request);
        if ($errors !== []) {
            return $this->backWithErrors('/treinamentos/novo', $errors, $request->all());
        }
        $id = (new TrainingRepository())->createModule($data + ['status' => 'rascunho', 'created_by' => Auth::id()], $questions);
        AuditLogger::log('training.created', 'training', $id, null, ['titulo' => $data['title'], 'perguntas' => count($questions)]);
        return $this->success("/treinamentos/{$id}", 'Treinamento criado como rascunho. Publique e designe os participantes.');
    }

    public function show(Request $request, int $id): Response
    {
        $repo = new TrainingRepository();
        $module = $repo->findOrFail($id);
        $manager = Auth::can(P::TRAININGS_MANAGE);
        if (!$manager && $module['status'] !== 'publicado') {
            throw new HttpException(404);
        }
        return $this->view('trainings/show', [
            'title' => $module['title'],
            'm' => $module,
            'questionsCount' => count($repo->questions($id)),
            'enrollment' => $repo->enrollment($id, (int) Auth::id()),
            'enrollments' => $manager ? $repo->enrollments($id) : [],
            'departments' => $manager ? (new DepartmentRepository())->options() : [],
            'users' => $manager ? (new UserRepository())->options() : [],
            'manager' => $manager,
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $repo = new TrainingRepository();
        $module = $repo->findOrFail($id);
        return $this->form($module, $repo->questions($id), 'Editar treinamento');
    }

    public function update(Request $request, int $id): Response
    {
        $repo = new TrainingRepository();
        $before = $repo->findOrFail($id);
        [$data, $questions, $errors] = $this->parse($request);
        if ($errors !== []) {
            return $this->backWithErrors("/treinamentos/{$id}/editar", $errors, $request->all());
        }
        $repo->saveModule($id, $data, $questions);
        AuditLogger::log('training.updated', 'training', $id, $before, $data + ['perguntas' => count($questions)]);
        return $this->success("/treinamentos/{$id}", 'Treinamento atualizado.');
    }

    public function status(Request $request, int $id): Response
    {
        $repo = new TrainingRepository();
        $module = $repo->findOrFail($id);
        $status = (string) $request->input('status');
        if (!isset(Labels::TRAINING_STATUS[$status])) {
            throw new HttpException(400);
        }
        if ($status === 'publicado' && $repo->questions($id) === []) {
            return $this->failure("/treinamentos/{$id}", 'Cadastre ao menos uma pergunta de avaliação antes de publicar.');
        }
        $repo->setStatus($id, $status);
        AuditLogger::log('training.status', 'training', $id, ['status' => $module['status']], ['status' => $status]);
        return $this->success("/treinamentos/{$id}", 'Situação alterada para ' . mb_strtolower(Labels::TRAINING_STATUS[$status]) . '.');
    }

    /** Designa participantes: todos os usuários ativos, setores ou pessoas. */
    public function assign(Request $request, int $id): Response
    {
        $repo = new TrainingRepository();
        $module = $repo->findOrFail($id);
        if ($module['status'] !== 'publicado') {
            return $this->failure("/treinamentos/{$id}", 'Publique o treinamento antes de designar participantes.');
        }
        $due = (string) $request->input('due_date');
        $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null;
        $users = (new UserRepository())->listWithDepartment();
        $active = array_filter($users, static fn (array $u): bool => $u['status'] === 'active');
        $target = (string) $request->input('target');
        $departments = array_map('intval', $request->inputArray('departments'));
        $selected = array_map('intval', $request->inputArray('users'));
        $ids = match ($target) {
            'todos' => array_column($active, 'id'),
            'setores' => array_column(array_filter($active, static fn (array $u): bool => in_array((int) $u['department_id'], $departments, true)), 'id'),
            default => array_values(array_intersect(array_column($active, 'id'), $selected)),
        };
        $ids = array_map('intval', $ids);
        if ($ids === []) {
            return $this->failure("/treinamentos/{$id}#designar", 'Nenhum participante selecionado.');
        }
        $added = $repo->enroll($id, $ids, Auth::id(), $due);
        AuditLogger::log('training.assigned', 'training', $id, null, ['participantes' => $added, 'prazo' => $due]);
        return $this->success("/treinamentos/{$id}", "{$added} participante(s) designado(s)" . ($added < count($ids) ? '; os demais já estavam inscritos.' : '.'));
    }

    /** Conteúdo + avaliação para o participante. */
    public function quiz(Request $request, int $id): Response
    {
        $repo = new TrainingRepository();
        $module = $repo->findOrFail($id);
        if ($module['status'] !== 'publicado') {
            throw new HttpException(404);
        }
        $enrollment = $repo->enrollment($id, (int) Auth::id());
        if ($enrollment === null) {
            $repo->enroll($id, [(int) Auth::id()], null, null); // inscrição espontânea
            $enrollment = $repo->enrollment($id, (int) Auth::id());
        }
        $repo->markStarted((int) $enrollment['id']);
        return $this->view('trainings/quiz', [
            'title' => 'Avaliação · ' . $module['title'],
            'm' => $module,
            'questions' => $repo->questions($id),
            'enrollment' => $enrollment,
        ]);
    }

    public function submit(Request $request, int $id): Response
    {
        $repo = new TrainingRepository();
        $module = $repo->findOrFail($id);
        $enrollment = $repo->enrollment($id, (int) Auth::id());
        if ($module['status'] !== 'publicado' || $enrollment === null) {
            throw new HttpException(404);
        }
        $answers = $request->inputArray('answer');
        $result = TrainingService::submit($module, $enrollment, $answers);
        if ($result['passed']) {
            return $this->success('/treinamentos/certificado/' . $result['certificate'], "Parabéns! Você foi aprovado(a) com nota {$result['score']}.");
        }
        return $this->failure("/treinamentos/{$id}", "Nota {$result['score']}: abaixo do mínimo de {$module['passing_score']}. Revise o conteúdo e tente de novo.");
    }

    /** Certificado (o próprio participante ou quem gerencia treinamentos). */
    public function certificate(Request $request, string $code): Response
    {
        $cert = (new TrainingRepository())->certificate($code);
        if ($cert === null || ((int) $cert['user_id'] !== Auth::id() && !Auth::can(P::TRAININGS_MANAGE))) {
            throw new HttpException(404);
        }
        return $this->view('trainings/certificate', [
            'title' => 'Certificado',
            'c' => $cert,
            'tenant' => (new TenantRepository())->current(),
            'verifyUrl' => absolute_url('/certificado/' . $code),
        ]);
    }

    /** Verificação pública da autenticidade do certificado. */
    public function verify(Request $request, string $code): Response
    {
        return $this->view('trainings/verify', [
            'title' => 'Verificação de certificado',
            'code' => $code,
            'cert' => TrainingRepository::verifyCertificate($code),
        ], 'public');
    }

    public function importTemplate(Request $request): Response
    {
        if (($limit = SubscriptionService::limitReached('trainings')) !== null) {
            return $this->failure('/treinamentos', $limit);
        }
        $template = Templates::lgpdTraining();
        $repo = new TrainingRepository();
        if ($repo->titleExists($template['title'])) {
            return $this->failure('/treinamentos', 'O treinamento modelo já existe na lista.');
        }
        $id = $repo->createModule([
            'title' => $template['title'], 'summary' => $template['summary'], 'content' => $template['content'],
            'duration_minutes' => $template['duration_minutes'], 'passing_score' => 70, 'certificate_months' => 12,
            'status' => 'rascunho', 'created_by' => Auth::id(),
        ], $template['questions']);
        AuditLogger::log('training.created', 'training', $id, null, ['titulo' => $template['title'], 'origem' => 'modelo']);
        return $this->success("/treinamentos/{$id}", 'Treinamento modelo criado como rascunho. Revise, publique e designe os participantes.');
    }

    private function form(array $module, array $questions, string $title): Response
    {
        return $this->view('trainings/form', ['title' => $title, 'm' => $module, 'questions' => $questions, 'slots' => self::QUESTION_SLOTS]);
    }

    /** @return array{0: array<string, mixed>, 1: list<array{question: string, options: list<string>, correct: int}>, 2: array<string, string>} */
    private function parse(Request $request): array
    {
        $v = Validator::make($request->all(), [
            'title' => 'required|max:190',
            'summary' => 'nullable|max:400',
            'content' => 'required|min:50|max:100000',
            'duration_minutes' => 'required|int',
            'passing_score' => 'required|int',
            'certificate_months' => 'required|in:6,12,24,36',
        ], ['title' => 'o título', 'content' => 'o conteúdo', 'duration_minutes' => 'a duração', 'passing_score' => 'a nota mínima']);
        $data = $v->validated();
        if (!$v->fails() && ((int) $data['passing_score'] < 1 || (int) $data['passing_score'] > 100)) {
            $v->addError('passing_score', 'A nota mínima vai de 1 a 100.');
        }
        $texts = $request->inputArray('q_text');
        $options = $request->all()['q_option'] ?? [];
        $corrects = $request->inputArray('q_correct');
        $questions = [];
        for ($i = 0; $i < self::QUESTION_SLOTS; $i++) {
            $text = trim((string) ($texts[$i] ?? ''));
            if ($text === '') {
                continue;
            }
            // Alternativas vazias são descartadas; o índice da correta é ajustado à nova posição.
            $marked = (int) ($corrects[$i] ?? -1);
            $opts = [];
            $correct = -1;
            foreach (is_array($options[$i] ?? null) ? $options[$i] : [] as $k => $option) {
                $option = mb_substr(trim((string) $option), 0, 300);
                if ($option === '') {
                    continue;
                }
                if ((int) $k === $marked) {
                    $correct = count($opts);
                }
                $opts[] = $option;
            }
            if (count($opts) < 2 || $correct < 0) {
                $v->addError('q_text', 'Pergunta ' . ($i + 1) . ': informe ao menos duas alternativas e marque a correta.');
                continue;
            }
            $questions[] = ['question' => mb_substr($text, 0, 500), 'options' => $opts, 'correct' => $correct];
        }
        return [[
            'title' => $data['title'],
            'summary' => $data['summary'],
            'content' => str_replace("\r\n", "\n", (string) $request->input('content')),
            'duration_minutes' => (int) $data['duration_minutes'],
            'passing_score' => (int) $data['passing_score'],
            'certificate_months' => (int) $data['certificate_months'],
        ], $questions, $v->errors()];
    }
}
