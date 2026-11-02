<?php

declare(strict_types=1);

namespace Isis\Support;

use Isis\Repositories\PlatformRepository;

/**
 * Termos de uso, aviso de privacidade e acordo de tratamento de dados (operador).
 * A identificação da AiCode vem das configurações da plataforma; a versão vai para a auditoria no aceite.
 */
final class LegalDocs
{
    public const VERSION = '02/11/2026';

    /** Campos editáveis no painel da plataforma: chave => rótulo. */
    public const FIELDS = [
        'legal_name' => 'Razão social da AiCode',
        'legal_cnpj' => 'CNPJ',
        'legal_address' => 'Endereço',
        'privacy_email' => 'E-mail de privacidade',
        'dpo_name' => 'Encarregado (DPO)',
        'subprocessors' => 'Suboperadores',
    ];

    public const PENDING = '[a definir]';

    /** @return array<string, string> valores com marcador para o que falta preencher */
    public static function info(): array
    {
        $repo = new PlatformRepository();
        $info = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $info[$key] = (string) ($repo->setting($key, '') ?? '');
        }
        return $info;
    }

    /** @return list<string> rótulos dos campos obrigatórios ainda vazios (suboperadores é opcional) */
    public static function missing(): array
    {
        $info = self::info();
        $missing = [];
        foreach (self::FIELDS as $key => $label) {
            if ($key !== 'subprocessors' && trim($info[$key]) === '') {
                $missing[] = $label;
            }
        }
        return $missing;
    }

    public static function show(string $value): string
    {
        return trim($value) === '' ? self::PENDING : $value;
    }
}
