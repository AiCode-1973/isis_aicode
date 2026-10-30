<?php

declare(strict_types=1);

use Isis\Auth\Auth;
use Isis\Auth\Role;
use Isis\Core\Config;
use Isis\Core\Csrf;
use Isis\Core\Session;

/** Escapa texto para HTML (use em toda saída de dados). */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/** URL absoluta (para e-mails). */
function absolute_url(string $path): string
{
    return Config::get('app.url') . '/' . ltrim($path, '/');
}

/** Arquivo estático com versão para cache. */
function asset(string $path): string
{
    $file = APP_ROOT . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : APP_VERSION;
    return '/assets/' . ltrim($path, '/') . '?v=' . $version;
}

function csrf_field(): string
{
    return Csrf::field();
}

/** Valor antigo do formulário (após erro de validação). */
function old(string $key, mixed $default = ''): string
{
    $old = Session::getFlash('_old', []);
    $value = $old[$key] ?? $default ?? '';
    return is_scalar($value) ? (string) $value : '';
}

/** Lista antiga (checkboxes múltiplos) ou o valor atual do registro. @return list<string> */
function old_list(string $key, array $current = []): array
{
    $old = Session::getFlash('_old');
    if (is_array($old)) {
        $value = $old[$key] ?? [];
        return is_array($value) ? array_map('strval', $value) : [];
    }
    return array_map('strval', $current);
}

/** Há dados antigos de formulário (após erro de validação)? */
function has_old(): bool
{
    return is_array(Session::getFlash('_old'));
}

/** Mensagem de erro do campo, já escapada. */
function field_error(string $key): string
{
    $errors = Session::getFlash('_errors', []);
    return isset($errors[$key]) ? '<p class="field-error" id="err-' . e($key) . '">' . e($errors[$key]) . '</p>' : '';
}

function has_error(string $key): bool
{
    return isset(Session::getFlash('_errors', [])[$key]);
}

/** Atributos de acessibilidade para campo com erro. */
function error_attrs(string $key): string
{
    return has_error($key) ? ' aria-invalid="true" aria-describedby="err-' . e($key) . '"' : '';
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function auth_user(): ?array
{
    return Auth::user();
}

function role_label(?string $role): string
{
    return Role::tryFrom((string) $role)?->label() ?? '—';
}

function fmt_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $time = strtotime($value);
    return $time === false ? '—' : date('d/m/Y H:i', $time);
}

function fmt_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $time = strtotime($value);
    return $time === false ? '—' : date('d/m/Y', $time);
}

/** Formata CNPJ numérico ou alfanumérico: AA.AAA.AAA/AAAA-DD */
function fmt_cnpj(?string $cnpj): string
{
    $c = (string) $cnpj;
    if (strlen($c) !== 14) {
        return $c;
    }
    return substr($c, 0, 2) . '.' . substr($c, 2, 3) . '.' . substr($c, 5, 3) . '/' . substr($c, 8, 4) . '-' . substr($c, 12, 2);
}

/** Código legível de registros: OP-001, RSK-012, ACT-003. */
function code(string $prefix, int $seq): string
{
    return sprintf('%s-%03d', $prefix, $seq);
}

function fmt_bytes(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
}

/** Classe de largura (w-0..w-100, passos de 10) para barras sem estilo inline. */
function width_class(float $percent): string
{
    return 'w-' . (int) (round(max(0, min(100, $percent)) / 10) * 10);
}

function label(array $map, string|int|null $value): string
{
    return Isis\Support\Labels::of($map, $value);
}

/** Selo do nível de risco. */
function risk_badge(int $likelihood, int $impact): string
{
    $level = Isis\Services\RiskMatrix::levelOf($likelihood, $impact);
    return '<span class="badge ' . Isis\Services\RiskMatrix::badge($level) . '">' . e(Isis\Services\RiskMatrix::label($level)) . ' · ' . ($likelihood * $impact) . '</span>';
}

function initials(?string $name): string
{
    $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last) ?: '?';
}

/** Marca o item de menu ativo. */
function nav_active(string $prefix): string
{
    $path = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    return ($path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/')) ? ' is-active' : '';
}

/** Ícones SVG em linha (traço), sem dependências externas. */
function icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'audit' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close' => '<path d="M18 6L6 18M6 6l12 12"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'alert' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'building' => '<rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M9 7h1M14 7h1M9 11h1M14 11h1M9 15h1M14 15h1M10 21v-3h4v3"/>',
        'layers' => '<path d="M12 2l10 5-10 5L2 7l10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
        'scale' => '<path d="M12 3v18M5 7h14M7 7l-4 7a4 4 0 0 0 8 0zM17 7l-4 7a4 4 0 0 0 8 0z"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
        'graduation' => '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>',
        'truck' => '<rect x="1" y="6" width="14" height="11" rx="1"/><path d="M15 9h4l4 4v4h-8"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',
        'siren' => '<path d="M7 18v-6a5 5 0 0 1 10 0v6"/><path d="M5 21h14M12 3v2M4.2 6.2l1.4 1.4M19.8 6.2l-1.4 1.4"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'trash' => '<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/>',
        'refresh' => '<path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
        'arrow-right' => '<path d="M5 12h14M13 5l7 7-7 7"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    ];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}

/** Rótulos legíveis das ações de auditoria. */
function audit_label(string $action): string
{
    return [
        'tenant.registered' => 'Empresa cadastrada',
        'tenant.updated' => 'Dados da empresa alterados',
        'onboarding.completed' => 'Configuração inicial concluída',
        'auth.login' => 'Entrada no sistema',
        'auth.logout' => 'Saída do sistema',
        'auth.login_failed' => 'Tentativa de login recusada',
        'auth.login_blocked' => 'Login bloqueado por excesso de tentativas',
        'auth.email_verified' => 'E-mail confirmado',
        'auth.password_reset_requested' => 'Recuperação de senha solicitada',
        'auth.password_reset' => 'Senha redefinida',
        'user.invited' => 'Colaborador convidado',
        'user.updated' => 'Usuário alterado',
        'invitation.accepted' => 'Convite aceito',
        'invitation.resent' => 'Convite reenviado',
        'invitation.revoked' => 'Convite cancelado',
        'department.created' => 'Setor criado',
        'department.updated' => 'Setor renomeado',
        'department.deleted' => 'Setor excluído',
        'profile.updated' => 'Perfil atualizado',
        'profile.password_changed' => 'Senha alterada',
        'sessions.revoked' => 'Outras sessões encerradas',
        'audit.exported' => 'Trilha de auditoria exportada',
        'audit.verified' => 'Integridade da auditoria verificada',
        'activity.created' => 'Operação de tratamento criada',
        'activity.updated' => 'Operação de tratamento alterada',
        'activity.deleted' => 'Operação de tratamento excluída',
        'inventory.exported' => 'Inventário exportado',
        'catalog.created' => 'Item de catálogo criado',
        'catalog.updated' => 'Item de catálogo alterado',
        'catalog.deleted' => 'Item de catálogo excluído',
        'catalog.imported' => 'Sugestões do segmento importadas',
        'risk.created' => 'Risco registrado',
        'risk.updated' => 'Risco alterado',
        'risk.deleted' => 'Risco excluído',
        'action.created' => 'Ação criada',
        'action.updated' => 'Ação alterada',
        'action.progress' => 'Andamento de ação registrado',
        'action.deleted' => 'Ação excluída',
        'evidence.uploaded' => 'Evidência anexada',
        'evidence.downloaded' => 'Evidência baixada',
        'evidence.deleted' => 'Evidência excluída',
        'access.denied' => 'Acesso negado por permissão',
        'access.not_found' => 'Tentativa de acesso a registro inexistente ou de outra empresa',
    ][$action] ?? $action;
}
