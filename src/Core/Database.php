<?php

declare(strict_types=1);

namespace OezCMS\Core;

use PDO;

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
        $statement = $this->connection->prepare($sql);

        if (false === $statement) {
            throw new DatabaseException(sprintf('Failed to prepare statement: %s', $sql));
        }

        $statement->execute($parameters);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }
}
