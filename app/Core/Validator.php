<?php

declare(strict_types=1);

namespace Isis\Core;

/**
 * Validação de entrada no servidor. Regras separadas por "|":
 * required, nullable, email, min:n, max:n, cnpj, in:a,b, password, same:campo, accepted, int, date.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];
    /** @var array<string, mixed> */
    private array $clean = [];

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    private function __construct(private readonly array $input, array $rules, private readonly array $labels)
    {
        foreach ($rules as $field => $ruleString) {
            $this->validateField($field, explode('|', $ruleString));
        }
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    public static function make(array $input, array $rules, array $labels = []): self
    {
        return new self($input, $rules, $labels);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> Dados validados e normalizados */
    public function validated(): array
    {
        return $this->clean;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    /** @param list<string> $rules */
    private function validateField(string $field, array $rules): void
    {
        $raw = $this->input[$field] ?? null;
        $value = is_string($raw) ? trim($raw) : $raw;
        $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));

        if ($value === null || $value === '' || $value === []) {
            if (in_array('required', $rules, true)) {
                $this->addError($field, "Informe {$label}.");
            } elseif (in_array('accepted', $rules, true)) {
                $this->addError($field, "É necessário confirmar: {$label}.");
            }
            $this->clean[$field] = null;
            return;
        }
        if (!is_string($value)) {
            $this->addError($field, "Valor inválido em {$label}.");
            return;
        }

        foreach ($rules as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            $error = match ($name) {
                'email' => self::checkEmail($value, $label),
                'min' => mb_strlen($value) < (int) $arg ? "{$label} deve ter pelo menos {$arg} caracteres." : null,
                'max' => mb_strlen($value) > (int) $arg ? "{$label} deve ter no máximo {$arg} caracteres." : null,
                'cnpj' => self::isCnpj($value) ? null : 'CNPJ inválido. Confira os números (ou letras do novo CNPJ alfanumérico).',
                'in' => in_array($value, explode(',', (string) $arg), true) ? null : "Opção inválida em {$label}.",
                'password' => self::passwordProblem($value),
                'same' => $value === (is_string($this->input[(string) $arg] ?? null) ? trim($this->input[(string) $arg]) : null)
                    ? null : 'A confirmação não confere.',
                'int' => ctype_digit($value) ? null : "{$label} deve ser um número.",
                'date' => self::isDate($value) ? null : "{$label} deve ser uma data válida.",
                default => null,
            };
            if ($error !== null) {
                $this->addError($field, $error);
                return;
            }
        }

        $this->clean[$field] = match (true) {
            in_array('email', $rules, true) => mb_strtolower($value),
            in_array('cnpj', $rules, true) => self::normalizeCnpj($value),
            in_array('password', $rules, true) => $raw,
            default => $value,
        };
    }

    private static function checkEmail(string $value, string $label): ?string
    {
        return (filter_var($value, FILTER_VALIDATE_EMAIL) && mb_strlen($value) <= 190) ? null : "{$label}: e-mail inválido.";
    }

    public static function passwordProblem(string $password): ?string
    {
        if (mb_strlen($password) < 10 || mb_strlen($password) > 128) {
            return 'A senha deve ter entre 10 e 128 caracteres.';
        }
        if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
            return 'A senha deve ter letras maiúsculas, minúsculas e números.';
        }
        return null;
    }

    public static function normalizeCnpj(string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));
    }

    /**
     * Valida CNPJ numérico e alfanumérico (vigente desde jul/2026): 12 caracteres [A-Z0-9] + 2 dígitos verificadores,
     * calculados em módulo 11 sobre o valor ASCII - 48 de cada caractere.
     */
    public static function isCnpj(string $value): bool
    {
        $cnpj = self::normalizeCnpj($value);
        if (!preg_match('/^[A-Z0-9]{12}\d{2}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }
        $values = array_map(static fn (string $c): int => ord($c) - 48, str_split($cnpj));
        foreach ([12, 13] as $length) {
            $sum = 0;
            $weight = $length - 7;
            for ($i = 0; $i < $length; $i++) {
                $sum += $values[$i] * $weight;
                $weight = $weight === 2 ? 9 : $weight - 1;
            }
            $digit = $sum % 11 < 2 ? 0 : 11 - $sum % 11;
            if ($values[$length] !== $digit) {
                return false;
            }
        }
        return true;
    }

    private static function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
