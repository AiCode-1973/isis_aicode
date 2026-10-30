<?php

declare(strict_types=1);

namespace Isis\Services;

use Isis\Audit\AuditLogger;
use Isis\Auth\Auth;
use Isis\Auth\TenantContext;
use Isis\Repositories\AttachmentRepository;

/**
 * Upload seguro de evidências: tipos permitidos pelo conteúdo real (finfo), limite de tamanho,
 * nome aleatório, armazenamento fora da raiz pública e hash SHA-256 para comprovar integridade.
 */
final class EvidenceService
{
    public const int MAX_BYTES = 10 * 1024 * 1024;

    /** MIME detectado => extensões aceitas */
    public const array ALLOWED = [
        'application/pdf' => ['pdf'],
        'image/png' => ['png'],
        'image/jpeg' => ['jpg', 'jpeg'],
        'text/plain' => ['txt', 'csv'],
        'text/csv' => ['csv'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
        'application/vnd.oasis.opendocument.text' => ['odt'],
        'application/vnd.oasis.opendocument.spreadsheet' => ['ods'],
        'application/zip' => ['docx', 'xlsx', 'pptx', 'odt', 'ods'], // alguns servidores identificam Office/ODF como zip
    ];

    /** Tipos de registro que aceitam evidências. */
    public const array ENTITIES = ['action', 'risk', 'activity'];

    /**
     * Valida e grava o arquivo. Retorna o id do anexo ou lança \DomainException com mensagem para o usuário.
     *
     * @param array<string, mixed>|null $file Item de $_FILES
     */
    public static function store(?array $file, string $entityType, int $entityId, ?string $description): int
    {
        if (!in_array($entityType, self::ENTITIES, true)) {
            throw new \DomainException('Tipo de registro inválido.');
        }
        if ($file === null || !isset($file['error']) || is_array($file['error'])) {
            throw new \DomainException('Selecione um arquivo.');
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new \DomainException('Arquivo maior que o permitido pelo servidor.');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new \DomainException('Falha no envio do arquivo. Tente novamente.');
        }
        return self::persist((string) $file['tmp_name'], (string) $file['name'], (int) $file['size'], $entityType, $entityId, $description, true);
    }

    /** Também usado pelos dados demonstrativos e testes (arquivo já no disco). */
    public static function persist(string $path, string $originalName, int $size, string $entityType, int $entityId, ?string $description, bool $uploaded = false): int
    {
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \DomainException('O arquivo deve ter até 10 MB.');
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$mime]) || !in_array($extension, self::ALLOWED[$mime], true)) {
            throw new \DomainException('Tipo de arquivo não permitido. Envie PDF, imagem (PNG/JPG), texto/CSV ou documento do Office/LibreOffice.');
        }

        $tenantId = TenantContext::id();
        $dir = self::directory($tenantId);
        $stored = bin2hex(random_bytes(20));
        $target = $dir . '/' . $stored;
        $ok = $uploaded ? move_uploaded_file($path, $target) : copy($path, $target);
        if (!$ok) {
            throw new \DomainException('Não foi possível gravar o arquivo no servidor.');
        }
        @chmod($target, 0640);

        $safeName = mb_substr((string) preg_replace('/[^\pL\pN ._()-]+/u', '_', basename($originalName)), 0, 190) ?: 'arquivo';
        $id = (new AttachmentRepository())->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'original_name' => $safeName,
            'stored_name' => $stored,
            'mime' => $mime === 'application/zip' ? self::officeMime($extension) : $mime,
            'size_bytes' => $size,
            'sha256' => hash_file('sha256', $target),
            'description' => $description !== null ? mb_substr($description, 0, 255) : null,
            'uploaded_by' => Auth::id(),
        ]);
        AuditLogger::log('evidence.uploaded', $entityType, $entityId, null, ['arquivo' => $safeName, 'tamanho' => $size, 'evidencia' => $id]);
        return $id;
    }

    public static function path(array $attachment): string
    {
        return self::directory((int) $attachment['tenant_id']) . '/' . $attachment['stored_name'];
    }

    public static function delete(array $attachment): void
    {
        (new AttachmentRepository())->remove((int) $attachment['id']);
        $file = self::path($attachment);
        if (is_file($file)) {
            @unlink($file);
        }
        AuditLogger::log('evidence.deleted', $attachment['entity_type'], (int) $attachment['entity_id'], ['arquivo' => $attachment['original_name'], 'sha256' => $attachment['sha256']], null);
    }

    /** Remove fisicamente os arquivos de um registro excluído. */
    public static function purgeEntity(string $type, int $id): void
    {
        foreach ((new AttachmentRepository())->removeForEntity($type, $id) as $attachment) {
            $file = self::path($attachment);
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private static function directory(int $tenantId): string
    {
        $dir = APP_ROOT . '/storage/uploads/' . $tenantId;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \DomainException('Pasta de evidências indisponível.');
        }
        return $dir;
    }

    private static function officeMime(string $extension): string
    {
        return [
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        ][$extension] ?? 'application/octet-stream';
    }
}
