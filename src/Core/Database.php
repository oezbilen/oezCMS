<?php

declare(strict_types=1);

namespace OezCMS\Core;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

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
     * @template T
     *
     * @param callable(self): T $callback
     *
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->connection->beginTransaction();

        try {
            $result = $callback($this);

            $this->connection->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function run(string $sql, array $parameters): PDOStatement
    {
        try {
            $statement = $this->connection->prepare($sql);

            if (false === $statement) {
                throw new DatabaseException(sprintf('Failed to prepare statement: %s', $sql));
            }

            $statement->execute($parameters);

            return $statement;
        } catch (PDOException $exception) {
            throw new DatabaseException(
                sprintf('Database query failed: %s', $exception->getMessage()),
                (int) $exception->getCode(),
                $exception,
            );
        }
    }
}
