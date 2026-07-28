<?php

declare(strict_types=1);

namespace OezCMS\Core;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * Every failure the container itself is responsible for.
 *
 * Not final: this is the base of a two-case hierarchy that PSR-11 requires,
 * and catching it has to keep catching the narrower case below it.
 */
class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
}
