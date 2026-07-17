<?php

declare(strict_types=1);

namespace OezCMS\Core;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

final class Database
{
    private const string IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]{0,63}$/';
    private const string SAVEPOINT_PREFIX = 'oezcms_sp_';
    private int $transactionLevel = 0;

    public function __construct(private readonly PDO $connection)
    {
    }

    private function savepointName(int $level): string
    {
        return self::SAVEPOINT_PREFIX . $level;
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
     * @param  array<string, mixed>                   $parameters
     * @return list<array<int, array<string, mixed>>>
     */
    public function callProcedure(string $procedure, array $parameters = []): array
    {
        if (1 !== preg_match(self::IDENTIFIER_PATTERN, $procedure)) {
            throw new DatabaseException(message: sprintf('Invalid procedure name: %s', $procedure));
        }

        foreach (array_keys($parameters) as $key) {
            if (1 !== preg_match(self::IDENTIFIER_PATTERN, $key)) {
                throw new DatabaseException(message: sprintf('Invalid procedure parameter name: %s', $key));
            }
        }

        $placeholders = implode(', ', array_map(
            static fn (string $key): string => ':' . $key,
            array_keys($parameters),
        ));

        $statement = $this->run(sprintf('CALL %s(%s)', $procedure, $placeholders), $parameters);
        $resultSets = [];

        try {
            do {
                /** @var array<int, array<string, mixed>> $rows */
                $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
                $resultSets[] = $rows;
            } while ($statement->nextRowset());

            // PDO_MYSQL exposes the procedure completion packet as the final
            // empty row set. It is removed by position because columnCount()
            // does not identify it reliably across driver versions.
            // The CALL always yields at least the final status row set, so the list
            // is guaranteed to be non-empty here.
            array_pop($resultSets);
        } catch (PDOException $exception) {
            throw new DatabaseException(
                message: sprintf('Procedure call failed: %s', $exception->getMessage()),
                sql: sprintf('CALL %s(%s)', $procedure, $placeholders),
                parameters: $parameters,
                code: (int) $exception->getCode(),
                previous: $exception,
            );
        } finally {
            try {
                $statement->closeCursor();
            } catch (PDOException) {
                // Cleanup must not mask why the procedure call failed.
            }
        }

        return $resultSets;
    }

    public function lastInsertId(): string
    {
        $id = $this->connection->lastInsertId();

        if (false === $id) {
            throw new DatabaseException(message: 'Unable to retrieve the last insert id.');
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

        $this->beginOrSavepoint($level);

        ++$this->transactionLevel;

        try {
            $result = $callback($this);

            $this->commitOrRelease($level);

            return $result;
        } catch (Throwable $exception) {
            $this->rollBackOrToSavepoint($level);

            throw $exception;
        } finally {
            --$this->transactionLevel;
        }
    }

    private function beginOrSavepoint(int $level): void
    {
        try {
            if (0 === $level) {
                $this->connection->beginTransaction();
            } else {
                $this->connection->exec(sprintf('SAVEPOINT %s', $this->savepointName($level)));
            }
        } catch (PDOException $exception) {
            throw new DatabaseException(
                message: sprintf('Failed to start transaction: %s', $exception->getMessage()),
                code: (int) $exception->getCode(),
                previous: $exception,
            );
        }
    }

    private function commitOrRelease(int $level): void
    {
        try {
            if (0 === $level) {
                $this->connection->commit();
            } else {
                $this->connection->exec(sprintf('RELEASE SAVEPOINT %s', $this->savepointName($level)));
            }
        } catch (PDOException $exception) {
            throw new DatabaseException(
                message: sprintf('Failed to commit transaction: %s', $exception->getMessage()),
                code: (int) $exception->getCode(),
                previous: $exception,
            );
        }
    }

    private function rollBackOrToSavepoint(int $level): void
    {
        try {
            if (0 === $level) {
                $this->connection->rollBack();
            } else {
                $this->connection->exec(sprintf('ROLLBACK TO SAVEPOINT %s', $this->savepointName($level)));
            }
        } catch (PDOException) {
            // Keep the original failure as the propagating exception;
            // a failed rollback must not mask why the transaction broke.
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
                throw new DatabaseException(
                    message: sprintf('Failed to prepare statement: %s', $sql),
                    sql: $sql,
                    parameters: $parameters,
                );
            }

            $statement->execute($parameters);

            return $statement;
        } catch (PDOException $exception) {
            throw new DatabaseException(
                message: sprintf('Database query failed: %s', $exception->getMessage()),
                sql: $sql,
                parameters: $parameters,
                code: (int) $exception->getCode(),
                previous: $exception,
            );
        }
    }
}
