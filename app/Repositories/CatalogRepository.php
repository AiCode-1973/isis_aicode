<?php

declare(strict_types=1);

namespace Isis\Repositories;

use Isis\Core\Database;

/**
 * Catálogos da empresa usados no inventário: categorias de dados, categorias de titulares e sistemas.
 */
final class CatalogRepository extends TenantScopedRepository
{
    /** tipo (usado na URL) => [tabela, coluna extra, rótulo, pivô, coluna do pivô] */
    public const array TYPES = [
        'dados' => ['data_categories', 'is_sensitive', 'Categorias de dados pessoais', 'activity_data_categories'],
        'titulares' => ['subject_categories', 'vulnerable', 'Categorias de titulares', 'activity_subject_categories'],
        'sistemas' => ['systems', 'hosting', 'Sistemas e locais de armazenamento', 'activity_systems'],
    ];

    private readonly string $tableName;

    public function __construct(public readonly string $type)
    {
        if (!isset(self::TYPES[$type])) {
            throw new \Isis\Core\HttpException(404);
        }
        $this->tableName = self::TYPES[$type][0];
    }

    #[\Override]
    protected function table(): string
    {
        return $this->tableName;
    }

    public function extraColumn(): string
    {
        return self::TYPES[$this->type][1];
    }

    public function label(): string
    {
        return self::TYPES[$this->type][2];
    }

    public function all(): array
    {
        $pivot = self::TYPES[$this->type][3];
        $column = $this->type === 'sistemas' ? 'system_id' : 'category_id';
        return Database::fetchAll(
            "SELECT c.*, (SELECT COUNT(*) FROM `{$pivot}` p WHERE p.tenant_id = c.tenant_id AND p.`{$column}` = c.id) AS uses
               FROM `{$this->table()}` c WHERE c.tenant_id = ? ORDER BY c.name",
            [$this->tenantId()],
        );
    }

    /** @return array<int, array{name: string, extra: mixed}> id => dados */
    public function options(): array
    {
        $options = [];
        foreach ($this->select('', [], 'name ASC') as $row) {
            $options[(int) $row['id']] = ['name' => $row['name'], 'extra' => $row[$this->extraColumn()]];
        }
        return $options;
    }

    public function nameExists(string $name, int $exceptId = 0): bool
    {
        return $this->count('name = ? AND id <> ?', [$name, $exceptId]) > 0;
    }

    public function create(string $name, int|string $extra): int
    {
        return $this->insert(['name' => $name, $this->extraColumn() => $extra, 'created_at' => date('Y-m-d H:i:s')]);
    }

    public function change(int $id, string $name, int|string $extra): void
    {
        $this->update($id, ['name' => $name, $this->extraColumn() => $extra]);
    }

    public function remove(int $id): void
    {
        $this->delete($id); // vínculos com operações caem em cascata
    }

    /** Filtra ids que pertencem à empresa atual. @param list<int> $ids @return list<int> */
    public function ownIds(array $ids): array
    {
        return array_values(array_intersect(array_map('intval', $ids), array_keys($this->options())));
    }
}
