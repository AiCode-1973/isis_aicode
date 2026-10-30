<?php

declare(strict_types=1);

namespace Isis\Core;

final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            403 => 'Você não tem permissão para acessar este recurso.',
            404 => 'Página ou registro não encontrado.',
            419 => 'Sua sessão expirou ou o formulário é inválido. Recarregue a página e tente novamente.',
            429 => 'Muitas tentativas. Aguarde alguns minutos e tente novamente.',
            default => 'Ocorreu um erro inesperado.',
        };
    }
}
