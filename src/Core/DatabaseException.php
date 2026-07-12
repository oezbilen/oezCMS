<?php

declare(strict_types=1);

namespace OezCMS\Core;

use RuntimeException;
use Throwable;

final class DatabaseException extends RuntimeException
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        string $message,
        private readonly string $sql = '',
        private readonly array $parameters = [],
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
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
