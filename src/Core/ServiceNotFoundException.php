<?php

declare(strict_types=1);

namespace OezCMS\Core;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown when nothing was ever registered under an identifier.
 *
 * A missing service is a question the caller may reasonably answer for itself;
 * a container that cannot build a registered service is not. Extending
 * ContainerException keeps the general catch working, while the interface is
 * what lets a caller ask for only the narrow case.
 */
final class ServiceNotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
