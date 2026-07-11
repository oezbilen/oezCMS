<?php

declare(strict_types=1);

namespace OezCMS\Core;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

final class Database
{
    private int $transactionLevel = 0;

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

    public function lastInsertId(): string
    {
        $id = $this->connection->lastInsertId();

        if (false === $id) {
            throw new DatabaseException('Unable to retrieve the last insert id.');
        }

        return $id;
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
        $level = $this->transactionLevel;

        if (0 === $level) {
            $this->connection->beginTransaction();
        } else {
            $this->connection->exec(sprintf('SAVEPOINT oezcms_sp_%d', $level));
        }

        ++$this->transactionLevel;

        try {
            $result = $callback($this);

            if (0 === $level) {
                $this->connection->commit();
            } else {
                $this->connection->exec(sprintf('RELEASE SAVEPOINT oezcms_sp_%d', $level));
            }

            return $result;
        } catch (Throwable $exception) {
            if (0 === $level) {
                $this->connection->rollBack();
            } else {
                $this->connection->exec(sprintf('ROLLBACK TO SAVEPOINT oezcms_sp_%d', $level));
            }

            throw $exception;
        } finally {
            --$this->transactionLevel;
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
