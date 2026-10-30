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

    /** Direitos do titular (art. 18 e art. 20 da LGPD). */
    public const array RIGHT_TYPE = [
        'confirmacao' => 'Confirmação da existência de tratamento (art. 18, I)',
        'acesso' => 'Acesso aos dados (art. 18, II)',
        'correcao' => 'Correção de dados incompletos, inexatos ou desatualizados (art. 18, III)',
        'anonimizacao' => 'Anonimização, bloqueio ou eliminação de dados desnecessários ou excessivos (art. 18, IV)',
        'portabilidade' => 'Portabilidade dos dados (art. 18, V)',
        'eliminacao' => 'Eliminação dos dados tratados com consentimento (art. 18, VI)',
        'compartilhamento' => 'Informação sobre com quem os dados são compartilhados (art. 18, VII)',
        'nao_consentir' => 'Informação sobre a possibilidade de não consentir (art. 18, VIII)',
        'revogacao' => 'Revogação do consentimento (art. 18, IX)',
        'oposicao' => 'Oposição ao tratamento (art. 18, § 2º)',
        'revisao_automatizada' => 'Revisão de decisão automatizada (art. 20)',
        'outro' => 'Outro pedido ou dúvida sobre privacidade',
    ];

    public const array REQUEST_CHANNEL = [
        'portal' => 'Canal on-line do titular',
        'email' => 'E-mail',
        'telefone' => 'Telefone',
        'presencial' => 'Presencial',
        'outro' => 'Outro',
    ];

    public const array REQUEST_STATUS = [
        'recebida' => 'Recebida',
        'em_verificacao' => 'Verificando identidade',
        'em_atendimento' => 'Em atendimento',
        'respondida' => 'Respondida',
        'indeferida' => 'Indeferida',
        'cancelada' => 'Cancelada',
    ];

    public const array INCIDENT_CATEGORY = [
        'acesso_indevido' => 'Acesso não autorizado',
        'vazamento' => 'Vazamento ou divulgação indevida',
        'perda' => 'Perda ou destruição de dados',
        'ransomware' => 'Ransomware ou malware',
        'phishing' => 'Phishing / engenharia social',
        'erro_humano' => 'Erro humano (envio errado, descarte inadequado)',
        'furto' => 'Furto ou extravio de equipamento ou documento',
        'indisponibilidade' => 'Indisponibilidade de sistema',
        'outro' => 'Outro',
    ];

    public const array SEVERITY = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'critica' => 'Crítica'];

    public const array INCIDENT_STATUS = [
        'comunicado' => 'Comunicado (aguardando análise)',
        'em_analise' => 'Em análise',
        'contido' => 'Contido',
        'resolvido' => 'Resolvido',
        'encerrado' => 'Encerrado',
    ];

    public const array RISK_TO_SUBJECTS = [
        'indeterminado' => 'Ainda não avaliado',
        'baixo' => 'Sem risco ou dano relevante aos titulares',
        'relevante' => 'Pode acarretar risco ou dano relevante aos titulares',
    ];

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
