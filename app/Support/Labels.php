<?php

declare(strict_types=1);

namespace Isis\Support;

/**
 * Rótulos em português dos valores enumerados guardados no banco.
 */
final class Labels
{
    public const array ACTIVITY_STATUS = [
        'rascunho' => 'Rascunho',
        'ativa' => 'Ativa',
        'em_revisao' => 'Em revisão',
        'descontinuada' => 'Descontinuada',
    ];

    public const array CONTROLLER_ROLE = [
        'controlador' => 'Controlador',
        'co-controlador' => 'Controlador conjunto',
        'operador' => 'Operador (em nome de outro controlador)',
    ];

    public const array DATA_SOURCE = [
        'titular' => 'Coletado do próprio titular',
        'terceiros' => 'Recebido de terceiros',
        'publica' => 'Fonte pública',
        'gerado' => 'Gerado pela própria empresa',
        'misto' => 'Fontes diversas',
    ];

    public const array SUBJECTS_VOLUME = [
        'ate_100' => 'Até 100 titulares',
        'ate_1000' => 'De 100 a 1.000',
        'ate_10000' => 'De 1.000 a 10.000',
        'ate_100000' => 'De 10.000 a 100.000',
        'acima' => 'Mais de 100.000',
    ];

    public const array RECIPIENT_TYPE = [
        'operador' => 'Operador / prestador de serviço',
        'controlador' => 'Outro controlador / parceiro',
        'orgao_publico' => 'Órgão público',
        'grupo_economico' => 'Empresa do mesmo grupo',
        'outro' => 'Outro',
    ];

    public const array SYSTEM_HOSTING = [
        'nuvem' => 'Nuvem / SaaS',
        'local' => 'Servidor próprio',
        'terceiro' => 'Sistema de terceiro',
        'papel' => 'Físico (papel)',
    ];

    public const array RISK_CATEGORY = [
        'seguranca' => 'Segurança da informação',
        'conformidade' => 'Conformidade legal',
        'direitos_titulares' => 'Direitos dos titulares',
        'terceiros' => 'Terceiros e fornecedores',
        'retencao' => 'Retenção e descarte',
        'transparencia' => 'Transparência',
        'outro' => 'Outro',
    ];

    public const array RISK_TREATMENT = [
        'mitigar' => 'Mitigar (reduzir)',
        'aceitar' => 'Aceitar',
        'transferir' => 'Transferir / compartilhar',
        'evitar' => 'Evitar (eliminar a atividade)',
    ];

    public const array RISK_STATUS = [
        'identificado' => 'Identificado',
        'em_tratamento' => 'Em tratamento',
        'mitigado' => 'Mitigado',
        'aceito' => 'Aceito',
        'encerrado' => 'Encerrado',
    ];

    public const array LIKELIHOOD = [1 => 'Rara', 2 => 'Improvável', 3 => 'Possível', 4 => 'Provável', 5 => 'Quase certa'];
    public const array IMPACT = [1 => 'Insignificante', 2 => 'Menor', 3 => 'Moderado', 4 => 'Maior', 5 => 'Gravíssimo'];

    public const array ACTION_STATUS = [
        'pendente' => 'Pendente',
        'em_andamento' => 'Em andamento',
        'concluida' => 'Concluída',
        'cancelada' => 'Cancelada',
    ];

    public const array PRIORITY = ['alta' => 'Alta', 'media' => 'Média', 'baixa' => 'Baixa'];

    /** @param array<string|int, string> $map */
    public static function of(array $map, string|int|null $value): string
    {
        return $value === null || $value === '' ? '—' : ($map[$value] ?? (string) $value);
    }

    /** @param array<string|int, string> $map */
    public static function rule(array $map): string
    {
        return 'in:' . implode(',', array_keys($map));
    }
}
