<?php

declare(strict_types=1);

namespace OezCMS\Core;

use RuntimeException;
use Throwable;

final class DatabaseException extends RuntimeException
{
    /**
     * Keeps the names of the bound parameters, never their values. An exception
     * travels further than the query that raised it, into logs, error reports
     * and serialized payloads, and a value may be a token, a reset code or an
     * e-mail address. The name is enough to tell which binding a failure concerns.
     *
     * The message is a different matter: after a failed query it ends with the
     * server's own error text, and MariaDB quotes values in some of those, a
     * duplicate key above all. It is meant for operators, never for end users.
     *
     * @param list<string> $parameterNames
     */
    public function __construct(
        string $message,
        private readonly string $sql = '',
        private readonly array $parameterNames = [],
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return list<string>
     */
    public function getParameterNames(): array
    {
        return $this->parameterNames;
    }
}
