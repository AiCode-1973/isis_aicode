<?php

declare(strict_types=1);

namespace Isis\Tests\Unit;

use Isis\Audit\AuditHasher;
use Isis\Audit\AuditLogger;
use PHPUnit\Framework\TestCase;

final class AuditTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function chain(int $size): array
    {
        $records = [];
        $previous = AuditHasher::GENESIS;
        for ($i = 1; $i <= $size; $i++) {
            $record = [
                'id' => $i, 'tenant_id' => 7, 'user_id' => 3, 'user_name' => 'Ana', 'action' => 'user.updated',
                'entity_type' => 'user', 'entity_id' => $i, 'ip' => '10.0.0.1', 'changes' => '{"role":"gestor"}',
                'created_at' => "2026-10-29 10:00:0{$i}.000000",
            ];
            $record['prev_hash'] = $previous;
            $record['hash'] = AuditHasher::hash($previous, $record);
            $previous = $record['hash'];
            $records[] = $record;
        }
        return $records;
    }

    public function testCadeiaIntegraEValidada(): void
    {
        $result = AuditHasher::verify($this->chain(5));
        self::assertTrue($result['ok']);
        self::assertSame(5, $result['checked']);
    }

    public function testAlteracaoNoMeioDaCadeiaEDetectada(): void
    {
        $records = $this->chain(5);
        $records[2]['changes'] = '{"role":"admin"}';
        $result = AuditHasher::verify($records);
        self::assertFalse($result['ok']);
        self::assertSame(3, $result['broken_id']);
    }

    public function testExclusaoDeRegistroEDetectada(): void
    {
        $records = $this->chain(5);
        unset($records[1]);
        self::assertFalse(AuditHasher::verify(array_values($records))['ok']);
    }

    public function testHashIgnoraTipoNumericoVindoDoBanco(): void
    {
        $record = ['tenant_id' => 7, 'entity_id' => 1, 'action' => 'x', 'created_at' => '2026-10-29 10:00:00.000000'];
        $fromDb = ['tenant_id' => '7', 'entity_id' => '1', 'action' => 'x', 'created_at' => '2026-10-29 10:00:00.000000'];
        self::assertSame(AuditHasher::hash('a', $record), AuditHasher::hash('a', $fromDb));
    }

    public function testDiffRegistraSoOQueMudouEMascaraSegredos(): void
    {
        $diff = AuditLogger::diff(
            ['name' => 'Ana', 'role' => 'gestor', 'department_id' => 3, 'password_hash' => 'antigo'],
            ['name' => 'Ana', 'role' => 'admin', 'department_id' => '3', 'password_hash' => 'novo'],
        );
        self::assertSame(['role', 'password_hash'], array_keys((array) $diff));
        self::assertSame(['de' => 'gestor', 'para' => 'admin'], $diff['role']);
        self::assertSame(['de' => '[protegido]', 'para' => '[protegido]'], $diff['password_hash']);

        self::assertSame(['depois' => ['token' => '[protegido]', 'email' => 'a@b.c']], AuditLogger::diff(null, ['token' => 'x', 'email' => 'a@b.c']));
        self::assertNull(AuditLogger::diff(['a' => 1], ['a' => '1']));
    }
}
