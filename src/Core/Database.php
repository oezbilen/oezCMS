<?php

declare(strict_types=1);

namespace OezCMS\Core;

use PDO;
use PDOStatement;

final class Database
{
    public function __construct(private readonly PDO $connection)
    {
    }

    /**
     * @param  array<string, mixed>             $parameters
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(string $sql, array $parameters = []): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->run($sql, $parameters)->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @param  array<string, mixed>      $parameters
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $parameters = []): ?array
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->run($sql, $parameters)->fetch(PDO::FETCH_ASSOC);

        return false === $row ? null : $row;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function execute(string $sql, array $parameters = []): int
    {
        return $this->run($sql, $parameters)->rowCount();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function run(string $sql, array $parameters): PDOStatement
    {
        $statement = $this->connection->prepare($sql);

        if (false === $statement) {
            throw new DatabaseException(sprintf('Failed to prepare statement: %s', $sql));
        }

        $statement->execute($parameters);

        return $statement;
    }
}
