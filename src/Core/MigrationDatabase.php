<?php

declare(strict_types=1);

namespace OezCMS\Core;

/**
 * The connection used to deploy schema changes.
 *
 * The container is keyed by class name, so a second connection to the same
 * database needs a type of its own. This wrapper adds no behaviour; it exists so
 * that the connection holding DDL privileges is named, and so that asking for
 * the wrong one is a type error rather than a runtime surprise.
 */
final class MigrationDatabase
{
    public function __construct(private readonly Database $database)
    {
    }

    public function database(): Database
    {
        return $this->database;
    }
}
