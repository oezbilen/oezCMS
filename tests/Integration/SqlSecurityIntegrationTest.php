<?php

declare(strict_types=1);

namespace OezCMS\Tests\Integration;

/**
 * Routines and views run with the rights of whoever calls them.
 *
 * MariaDB's default is the definer's rights, which would let the runtime do
 * through a routine whatever the deploy account that created it may do. The
 * schema is deployed fresh before each test, so these checks cover every
 * routine and view the deploy creates, including ones added later.
 */
final class SqlSecurityIntegrationTest extends SchemaDeploymentTestCase
{
    public function testEveryRoutineRunsWithTheCallersRights(): void
    {
        $types = $this->securityTypes(
            'SELECT ROUTINE_NAME AS name, SECURITY_TYPE AS security_type
               FROM information_schema.routines
              WHERE ROUTINE_SCHEMA = DATABASE()',
        );

        // Without any routine the check below would hold for nothing.
        self::assertNotSame([], $types);
        self::assertSame([], array_keys(array_diff($types, ['INVOKER'])));
    }

    public function testEveryViewRunsWithTheCallersRights(): void
    {
        $types = $this->securityTypes(
            'SELECT TABLE_NAME AS name, SECURITY_TYPE AS security_type
               FROM information_schema.views
              WHERE TABLE_SCHEMA = DATABASE()',
        );

        // Without any view the check below would hold for nothing.
        self::assertNotSame([], $types);
        self::assertSame([], array_keys(array_diff($types, ['INVOKER'])));
    }

    /**
     * @return array<string, string> security type by object name
     */
    private function securityTypes(string $sql): array
    {
        $types = [];

        foreach ($this->database->fetchAll($sql) as $row) {
            $name = $row['name'] ?? null;
            $type = $row['security_type'] ?? null;

            if (is_string($name) && is_string($type)) {
                $types[$name] = $type;
            }
        }

        return $types;
    }
}
