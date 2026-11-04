<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\Role;
use Isis\Core\Database;
use Isis\Core\Validator;
use Isis\Repositories\DepartmentRepository;
use Isis\Repositories\SegmentRepository;
use Isis\Repositories\TenantRepository;

/**
 * Assistente de configuração inicial: roteiro em etapas (empresa, áreas, dados e responsáveis) que termina
 * num plano sugerido de quais módulos configurar primeiro. As respostas ficam em tenants.onboarding_answers
 * para retomar de onde parou; nada é criado até a conclusão.
 */
final class OnboardingService
{
    /** Etapas na ordem do roteiro (código na URL => título). */
    public const array STEPS = [
        'empresa' => 'Empresa',
        'areas' => 'Áreas',
        'dados' => 'Dados',
        'responsaveis' => 'Responsáveis',
        'plano' => 'Plano sugerido',
    ];

    public const array SIZES = [
        'ate_10' => 'Até 10 pessoas',
        '11_50' => 'De 11 a 50 pessoas',
        '51_200' => 'De 51 a 200 pessoas',
        'mais_200' => 'Mais de 200 pessoas',
    ];

    public const array AUDIENCES = [
        'pessoas' => 'Clientes, pacientes ou alunos (pessoas físicas)',
        'empresas' => 'Outras empresas (clientes corporativos)',
    ];

    public const array CHANNELS = [
        'site' => 'Site com formulários ou cadastro',
        'loja' => 'Loja virtual ou aplicativo',
        'mensagens' => 'WhatsApp, redes sociais ou e-mail marketing',
        'presencial' => 'Atendimento presencial com cadastro',
        'cameras' => 'Câmeras de segurança ou controle de acesso',
    ];

    /** O que a empresa já tem hoje (diminui a prioridade do módulo correspondente). */
    public const array EXISTING = [
        'inventario' => 'Mapeamento de quais dados são usados e para quê',
        'politicas' => 'Políticas de privacidade e de segurança escritas',
        'treinamento' => 'Treinamento da equipe sobre proteção de dados',
        'contratos' => 'Contratos com fornecedores com cláusulas de proteção de dados',
        'incidentes' => 'Procedimento para incidentes de segurança',
        'canal' => 'Canal para atender pedidos de titulares',
    ];

    public const array ANSWERS = ['sim' => 'Sim', 'nao' => 'Não', 'nao_sei' => 'Não sei'];

    /** Categorias comuns a qualquer segmento, somadas às do modelo do segmento. */
    private const array COMMON_DATA = [
        'Dados de identificação', 'Dados de contato', 'Dados financeiros e de pagamento', 'Dados de colaboradores (RH e folha)',
        'Imagem e voz (fotos, vídeos, câmeras)', 'Dados de localização', 'Dados de navegação e cookies',
        'Dados de saúde', 'Dados biométricos', 'Dados de crianças e adolescentes',
    ];
    private const array COMMON_SUBJECTS = [
        'Clientes', 'Colaboradores', 'Candidatos a vagas', 'Visitantes', 'Representantes de fornecedores', 'Crianças e adolescentes',
    ];

    private const int MAX_OWNERS = 30;

    /** @return array<string, mixed> respostas salvas (vazio se o assistente nunca foi aberto) */
    public static function answers(array $tenant): array
    {
        $answers = json_decode((string) ($tenant['onboarding_answers'] ?? ''), true);
        return is_array($answers) ? $answers : [];
    }

    /** Primeira etapa ainda não respondida (ou o plano, se todas foram). */
    public static function nextStep(array $answers): string
    {
        foreach (array_keys(self::STEPS) as $step) {
            if ($step === 'plano' || !isset($answers[$step])) {
                return $step;
            }
        }
        return 'plano';
    }

    /** Uma etapa só abre depois das anteriores (evita pular direto para o plano). */
    public static function canOpen(string $step, array $answers): bool
    {
        foreach (array_keys(self::STEPS) as $s) {
            if ($s === $step) {
                return true;
            }
            if (!isset($answers[$s])) {
                return false;
            }
        }
        return false;
    }

    public static function previous(string $step): ?string
    {
        $keys = array_keys(self::STEPS);
        $i = array_search($step, $keys, true);
        return $i > 0 ? $keys[$i - 1] : null;
    }

    public static function following(string $step): string
    {
        $keys = array_keys(self::STEPS);
        $i = (int) array_search($step, $keys, true);
        return $keys[min($i + 1, count($keys) - 1)];
    }

    /** @return list<string> */
    public static function departmentSuggestions(string $segmentCode): array
    {
        return SegmentRepository::template($segmentCode)['departments'] ?? [];
    }

    /** @return list<string> sugestões do segmento primeiro, depois as comuns */
    public static function dataOptions(string $segmentCode): array
    {
        return self::merge(SegmentRepository::template($segmentCode)['data_categories'] ?? [], self::COMMON_DATA);
    }

    /** @return list<string> */
    public static function subjectOptions(string $segmentCode): array
    {
        return self::merge(SegmentRepository::template($segmentCode)['subject_categories'] ?? [], self::COMMON_SUBJECTS);
    }

    /**
     * Valida uma etapa. Devolve os dados normalizados para guardar ou os erros por campo.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $answers respostas anteriores (o segmento define as opções das etapas seguintes)
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function validate(string $step, array $input, array $answers, int $existingDepartments = 0): array
    {
        return match ($step) {
            'empresa' => self::validateCompany($input),
            'areas' => self::validateAreas($input, $existingDepartments),
            'dados' => self::validateData($input, (string) ($answers['empresa']['segment_code'] ?? 'outro')),
            'responsaveis' => self::validateOwners($input, $answers['areas']['departments'] ?? []),
            default => [[], []],
        };
    }

    /** @return array{0: array<string, mixed>, 1: array<string, string>} */
    private static function validateCompany(array $input): array
    {
        $v = Validator::make($input, [
            'segment_code' => 'required|in:' . implode(',', array_keys(SegmentRepository::options())),
            'size' => 'required|in:' . implode(',', array_keys(self::SIZES)),
        ], ['segment_code' => 'o segmento', 'size' => 'o tamanho da equipe']);
        $data = [
            'segment_code' => (string) ($input['segment_code'] ?? ''),
            'size' => (string) ($input['size'] ?? ''),
            'audiences' => self::pick($input['audiences'] ?? [], self::AUDIENCES),
            'channels' => self::pick($input['channels'] ?? [], self::CHANNELS),
            'existing' => self::pick($input['existing'] ?? [], self::EXISTING),
        ];
        if ($data['audiences'] === []) {
            $v->addError('audiences', 'Marque com quem a empresa se relaciona.');
        }
        return [$data, $v->errors()];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, string>} */
    private static function validateAreas(array $input, int $existingDepartments): array
    {
        $names = self::lines($input['departments'] ?? [], $input['other_departments'] ?? '', 120);
        $errors = [];
        if ($names === [] && $existingDepartments === 0) {
            $errors['departments'] = 'Selecione ou informe pelo menos uma área.';
        }
        if (count($names) > self::MAX_OWNERS) {
            $errors['departments'] = 'Informe no máximo ' . self::MAX_OWNERS . ' áreas agora; as demais podem ser criadas depois em Configurações.';
        }
        return [['departments' => $names], $errors];
    }

    /** @return array{0: array<string, mixed>, 1: array<string, string>} */
    private static function validateData(array $input, string $segmentCode): array
    {
        $data = self::lines(self::pickValues($input['data_categories'] ?? [], self::dataOptions($segmentCode)), $input['other_data'] ?? '', 150);
        $subjects = self::lines(self::pickValues($input['subject_categories'] ?? [], self::subjectOptions($segmentCode)), $input['other_subjects'] ?? '', 150);
        $out = [
            'data_categories' => $data,
            'subject_categories' => $subjects,
            'vendors' => (string) ($input['vendors'] ?? ''),
            'international' => (string) ($input['international'] ?? ''),
        ];
        $errors = [];
        if ($data === []) {
            $errors['data_categories'] = 'Marque pelo menos um tipo de dado.';
        }
        if ($subjects === []) {
            $errors['subject_categories'] = 'Marque de quem são os dados.';
        }
        foreach (['vendors' => 'Responda sobre fornecedores.', 'international' => 'Responda sobre dados fora do Brasil.'] as $field => $message) {
            if (!isset(self::ANSWERS[$out[$field]])) {
                $errors[$field] = $message;
            }
        }
        return [$out, $errors];
    }

    /**
     * @param list<string> $departments áreas escolhidas na etapa anterior (o índice liga cada responsável à área)
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private static function validateOwners(array $input, array $departments): array
    {
        $v = Validator::make($input, [
            'dpo_name' => 'required|min:3|max:150',
            'dpo_email' => 'required|email',
            'privacy_email' => 'nullable|email',
        ], ['dpo_name' => 'o nome do encarregado', 'dpo_email' => 'o e-mail do encarregado', 'privacy_email' => 'o e-mail do canal de privacidade']);
        $data = $v->validated();
        $out = [
            'dpo_name' => trim((string) ($data['dpo_name'] ?? '')),
            'dpo_email' => mb_strtolower(trim((string) ($data['dpo_email'] ?? ''))),
            'privacy_email' => $data['privacy_email'] !== null && $data['privacy_email'] !== '' ? mb_strtolower(trim((string) $data['privacy_email'])) : null,
            'invite_dpo' => !empty($input['invite_dpo']),
            'owners' => [],
        ];

        $names = is_array($input['owner_name'] ?? null) ? $input['owner_name'] : [];
        $emails = is_array($input['owner_email'] ?? null) ? $input['owner_email'] : [];
        $invites = is_array($input['owner_invite'] ?? null) ? $input['owner_invite'] : [];
        foreach ($departments as $i => $department) {
            $name = trim((string) (is_string($names[$i] ?? null) ? $names[$i] : ''));
            $email = mb_strtolower(trim((string) (is_string($emails[$i] ?? null) ? $emails[$i] : '')));
            if ($name === '' && $email === '') {
                continue;
            }
            if ($name === '' || mb_strlen($name) < 3 || mb_strlen($name) > 150) {
                $v->addError('owner_name_' . $i, 'Informe o nome do responsável por ' . $department . '.');
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $v->addError('owner_email_' . $i, 'E-mail inválido para ' . $department . '.');
            }
            $invite = !empty($invites[$i]) && $email !== '';
            $out['owners'][] = ['department' => $department, 'name' => $name, 'email' => $email, 'invite' => $invite];
        }
        return [$out, $v->errors()];
    }

    /**
     * Plano sugerido: cada módulo recebe uma pontuação pelas respostas, com os motivos em linguagem simples.
     * É uma ordem de trabalho sugerida, não uma avaliação jurídica.
     *
     * @return list<array{key: string, title: string, module: string, link: string, score: int, reasons: list<string>}>
     */
    public static function plan(array $answers): array
    {
        $company = $answers['empresa'] ?? [];
        $data = $answers['dados'] ?? [];
        $people = $answers['responsaveis'] ?? [];
        $areas = $answers['areas']['departments'] ?? [];
        $has = array_flip($company['existing'] ?? []);
        $channels = array_flip($company['channels'] ?? []);
        $size = (string) ($company['size'] ?? 'ate_10');

        $sensitive = array_values(array_filter($data['data_categories'] ?? [], [CatalogService::class, 'isSensitive']));
        $children = array_filter(
            [...($data['data_categories'] ?? []), ...($data['subject_categories'] ?? [])],
            static fn (string $n): bool => (bool) preg_match('/crian[çc]as|adolescentes|menores/iu', $n)
        ) !== [];
        $invites = count(array_filter($people['owners'] ?? [], static fn (array $o): bool => $o['invite'])) + (!empty($people['invite_dpo']) ? 1 : 0);
        $bigTeam = in_array($size, ['51_200', 'mais_200'], true);

        $items = [];
        $add = static function (string $key, string $title, string $module, string $link, int $score, array $reasons) use (&$items): void {
            $items[] = ['key' => $key, 'title' => $title, 'module' => $module, 'link' => $link, 'score' => $score, 'reasons' => array_values(array_filter($reasons))];
        };

        $add('inventario', 'Mapear as operações de tratamento', 'Inventário de dados', '/inventario', isset($has['inventario']) ? 70 : 100, [
            isset($has['inventario'])
                ? 'Vocês já têm um mapeamento: registrá-lo no ÍSIS liga cada operação aos riscos, fornecedores e pedidos de titulares.'
                : 'É a base do programa: riscos, relatório de impacto e respostas a titulares partem do inventário.',
            count($areas) > 1 ? 'Comece pelas áreas que mais usam dados pessoais entre as ' . count($areas) . ' informadas.' : null,
            ($data['international'] ?? '') === 'sim' ? 'Registre nas operações quais dados ficam fora do Brasil (transferência internacional, art. 33).' : null,
        ]);

        $add('equipe', 'Trazer os responsáveis de cada área', 'Usuários e perfis', '/usuarios', match (true) {
            $invites > 0 => 55,
            $size === 'ate_10' => 25,
            default => 80,
        }, [
            $invites > 0 ? plural($invites, 'convite será enviado', 'convites serão enviados') . ' ao concluir; acompanhe quem aceitou.' : null,
            $invites === 0 && $size !== 'ate_10' ? 'Com uma equipe desse tamanho, cada área deve registrar as próprias operações.' : null,
            $invites === 0 && $size === 'ate_10' ? 'Em equipes pequenas, uma ou duas pessoas costumam conduzir o programa.' : null,
        ]);

        $riskScore = 45 + ($sensitive !== [] ? 30 : 0) + ($children ? 15 : 0) + (isset($channels['cameras']) ? 5 : 0);
        $add('riscos', 'Avaliar os riscos das operações', 'Gestão de riscos', '/riscos', $riskScore, [
            $sensitive !== [] ? 'Há dados sensíveis (' . self::listing($sensitive) . '), que pedem controles mais fortes.' : null,
            $children ? 'Há dados de crianças ou adolescentes, que exigem atenção ao melhor interesse (art. 14).' : null,
            isset($channels['cameras']) ? 'Imagens de câmeras e controle de acesso merecem regras de guarda e de acesso.' : null,
            'A matriz de probabilidade e impacto mostra onde agir primeiro.',
        ]);

        if ($sensitive !== [] || $children) {
            $add('ripd', 'Preparar o relatório de impacto', 'Relatórios de impacto (RIPD)', '/ripd', 60 + ($sensitive !== [] && $bigTeam ? 10 : 0), [
                'Tratamentos com dados sensíveis ou de crianças podem exigir relatório de impacto (art. 38), a pedido da ANPD.',
                'Faça depois do inventário: o relatório parte das operações já mapeadas.',
            ]);
        }

        $vendors = (string) ($data['vendors'] ?? 'nao_sei');
        if ($vendors !== 'nao') {
            $add('fornecedores', 'Avaliar fornecedores que tratam dados', 'Fornecedores', '/fornecedores',
                ($vendors === 'sim' ? 70 : 50) + (($data['international'] ?? '') === 'sim' ? 10 : 0) - (isset($has['contratos']) ? 20 : 0), [
                $vendors === 'sim' ? 'Sistemas e prestadores que acessam os dados respondem junto com a empresa.' : 'Vale levantar quais sistemas e prestadores acessam os dados (nuvem, contabilidade, folha, marketing).',
                isset($has['contratos']) ? 'Os contratos já têm cláusulas: registre-os e acompanhe os vencimentos.' : 'Confira se os contratos têm cláusulas de proteção de dados.',
                match ($data['international'] ?? '') {
                    'sim' => 'Para dados fora do Brasil, verifique as garantias do fornecedor.',
                    'nao_sei' => 'Pergunte a cada fornecedor onde os dados ficam guardados: servidores fora do Brasil pedem cuidados extras (art. 33).',
                    default => null,
                },
            ]);
        }

        $online = array_intersect_key($channels, array_flip(['site', 'loja', 'mensagens'])) !== [];
        $add('titulares', 'Organizar o atendimento aos titulares', 'Solicitações de titulares', '/titulares',
            30 + (in_array('pessoas', $company['audiences'] ?? [], true) ? 30 : 0) + ($online ? 10 : 0) - (isset($has['canal']) ? 20 : 0), [
            in_array('pessoas', $company['audiences'] ?? [], true) ? 'Pessoas físicas podem pedir acesso, correção ou eliminação dos seus dados (art. 18).' : 'Mesmo atendendo empresas, contatos e colaboradores também são titulares.',
            'O ÍSIS oferece um canal público e controla o prazo de cada pedido.',
            $online ? 'Divulgue o canal no site, na loja virtual ou nas mensagens.' : null,
        ]);

        $add('politicas', isset($has['politicas']) ? 'Registrar as políticas existentes' : 'Escrever e aprovar as políticas', 'Políticas e documentos', '/politicas', isset($has['politicas']) ? 25 : 55, [
            isset($has['politicas']) ? 'Cadastre-as para controlar a validade e a ciência da equipe.' : 'Os modelos do ÍSIS ajudam a começar o aviso de privacidade e a política de segurança.',
        ]);

        $add('treinamentos', 'Treinar a equipe', 'Treinamentos', '/treinamentos',
            25 + match ($size) { '11_50' => 15, '51_200' => 25, 'mais_200' => 30, default => 0 } + (isset($has['treinamento']) ? 0 : 15), [
            isset($has['treinamento']) ? 'Registre os treinamentos já feitos e os próximos.' : 'Quem lida com os dados precisa saber o que fazer, e o treinamento fica registrado.',
            $bigTeam ? 'Em equipes maiores, os erros do dia a dia são a principal causa de incidentes.' : null,
        ]);

        $add('incidentes', 'Definir como tratar incidentes', 'Incidentes de segurança', '/incidentes',
            30 + (isset($has['incidentes']) ? 0 : 15) + ($sensitive !== [] ? 10 : 0), [
            isset($has['incidentes']) ? 'Registre o procedimento existente e use o ÍSIS para documentar cada caso.' : 'Combine quem avisa, quem avalia e quando comunicar a ANPD e os titulares.',
            $sensitive !== [] ? 'Com dados sensíveis, um vazamento tende a ter risco relevante.' : null,
        ]);

        usort($items, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return $items;
    }

    /**
     * Conclui o assistente: cria áreas e categorias, salva encarregado e segmento e envia os convites marcados.
     *
     * @return list<string> avisos dos convites que não puderam ser enviados
     */
    public static function complete(array $answers): array
    {
        $tenants = new TenantRepository();
        $before = $tenants->current();
        $company = $answers['empresa'];
        $people = $answers['responsaveis'];
        $data = $answers['dados'];
        $answers['concluido_em'] = date('Y-m-d H:i:s');
        unset($answers['pendente']);

        Database::transaction(function () use ($answers, $company, $people, $data, $tenants, $before): void {
            $departments = new DepartmentRepository();
            $count = 0;
            foreach ($answers['areas']['departments'] as $name) {
                if (!$departments->nameExists($name)) {
                    $departments->create($name);
                    $count++;
                }
            }
            $catalog = CatalogService::importSelected($data['data_categories'], $data['subject_categories']);
            $changes = [
                'segment_code' => $company['segment_code'],
                'dpo_name' => $people['dpo_name'],
                'dpo_email' => $people['dpo_email'],
                'privacy_email' => $people['privacy_email'] ?? $people['dpo_email'],
                'onboarding_completed_at' => $before['onboarding_completed_at'] ?? date('Y-m-d H:i:s'),
                'onboarding_answers' => json_encode($answers, JSON_UNESCAPED_UNICODE),
            ];
            $tenants->updateCurrent($changes);
            $audit = $changes;
            unset($audit['onboarding_answers']);
            AuditLogger::log('onboarding.completed', 'tenant', (int) $before['id'], $before, $audit + [
                'setores_criados' => $count,
                'categorias_dados_criadas' => $catalog['data'],
                'categorias_titulares_criadas' => $catalog['subjects'],
                'plano_sugerido' => array_column(array_slice(self::plan($answers), 0, 3), 'key'),
            ]);
        });

        // Convites depois da transação: cada um envia e-mail e pode esbarrar no limite do plano.
        $warnings = [];
        $departmentIds = array_flip((new DepartmentRepository())->options());
        $me = mb_strtolower((string) (Auth::user()['email'] ?? ''));
        $queue = [];
        if (!empty($people['invite_dpo']) && $people['dpo_email'] !== $me) {
            $queue[] = ['name' => $people['dpo_name'], 'email' => $people['dpo_email'], 'role' => Role::Dpo->value, 'department_id' => null];
        }
        foreach ($people['owners'] as $owner) {
            if ($owner['invite'] && $owner['email'] !== $me && $owner['email'] !== $people['dpo_email']) {
                $queue[] = ['name' => $owner['name'], 'email' => $owner['email'], 'role' => Role::Manager->value, 'department_id' => $departmentIds[$owner['department']] ?? null];
            }
        }
        foreach ($queue as $invite) {
            $error = AccountService::invite($invite);
            if ($error !== null) {
                $warnings[] = $invite['email'] . ': ' . $error;
            }
        }
        return $warnings;
    }

    /** Guarda as respostas de uma etapa (sem criar nada ainda). */
    public static function save(array $answers): void
    {
        (new TenantRepository())->updateCurrent(['onboarding_answers' => json_encode($answers, JSON_UNESCAPED_UNICODE)]);
    }

    /** @param list<string> $first @param list<string> $then @return list<string> */
    private static function merge(array $first, array $then): array
    {
        $out = [];
        $seen = [];
        foreach ([...$first, ...$then] as $name) {
            // "Dados de saúde" e "Dados de saúde (laudos...)" contam como o mesmo item.
            $key = mb_strtolower(self::base($name));
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $name;
            }
        }
        return $out;
    }

    /** @return list<string> chaves válidas marcadas */
    private static function pick(mixed $values, array $options): array
    {
        return is_array($values) ? array_values(array_intersect(array_keys($options), $values)) : [];
    }

    /** @param list<string> $options @return list<string> */
    private static function pickValues(mixed $values, array $options): array
    {
        return is_array($values) ? array_values(array_intersect($options, $values)) : [];
    }

    /** Marcados + "um por linha", sem repetição. @return list<string> */
    private static function lines(mixed $checked, mixed $text, int $max): array
    {
        $names = is_array($checked) ? array_map('trim', array_filter($checked, 'is_string')) : [];
        foreach (preg_split('/\R/', is_string($text) ? $text : '') ?: [] as $line) {
            $names[] = trim($line);
        }
        return self::merge(array_values(array_filter($names, static fn (string $n): bool => $n !== '' && mb_strlen($n) <= $max)), []);
    }

    /** @param list<string> $items */
    private static function listing(array $items): string
    {
        $items = array_map(static fn (string $s): string => mb_strtolower(mb_substr(self::base($s), 0, 1)) . mb_substr(self::base($s), 1), $items);
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }
        return implode(', ', array_slice($items, 0, -1)) . ' e ' . end($items);
    }

    /** Nome sem o detalhe entre parênteses. */
    private static function base(string $name): string
    {
        return trim((string) preg_replace('/\s*\(.*$/u', '', $name));
    }
}
