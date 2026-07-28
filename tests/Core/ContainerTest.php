<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\Container;
use OezCMS\Core\ContainerException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use stdClass;
use WeakReference;

final class ContainerTest extends TestCase
{
    public function testGetResolvesRegisteredFactory(): void
    {
        $container = new Container();
        $service = new stdClass();
        $container->set(stdClass::class, static fn (): stdClass => $service);

        self::assertSame($service, $container->get(stdClass::class));
    }

    public function testGetReturnsSameInstanceOnRepeatedCalls(): void
    {
        $container = new Container();
        $container->set(stdClass::class, static fn (): stdClass => new stdClass());

        self::assertSame(
            $container->get(stdClass::class),
            $container->get(stdClass::class),
        );
    }

    public function testGetThrowsExceptionForUnregisteredService(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);

        $container->get(stdClass::class);
    }

    public function testInstanceRegistersPrebuiltObject(): void
    {
        $container = new Container();
        $service = new stdClass();

        $container->instance(stdClass::class, $service);

        self::assertSame($service, $container->get(stdClass::class));
    }

    public function testHasReturnsTrueForRegisteredFactory(): void
    {
        $container = new Container();
        $container->set(stdClass::class, static fn (): stdClass => new stdClass());

        self::assertTrue($container->has(stdClass::class));
    }

    public function testHasReturnsTrueForRegisteredInstance(): void
    {
        $container = new Container();
        $container->instance(stdClass::class, new stdClass());

        self::assertTrue($container->has(stdClass::class));
    }

    public function testHasReturnsFalseForUnregisteredService(): void
    {
        $container = new Container();

        self::assertFalse($container->has(stdClass::class));
    }

    public function testGetDetectsCircularDependency(): void
    {
        $container = new Container();
        $container->set(
            stdClass::class,
            static fn (Container $c): stdClass => $c->get(stdClass::class),
        );

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/circular dependency/i');

        $container->get(stdClass::class);
    }

    public function testContainerStaysUsableAfterCircularDependencyError(): void
    {
        $container = new Container();
        $container->set(
            stdClass::class,
            static fn (Container $c): stdClass => $c->get(stdClass::class),
        );

        try {
            $container->get(stdClass::class);
        } catch (ContainerException) {
        }

        $service = new stdClass();
        $container->set(stdClass::class, static fn (): stdClass => $service);

        self::assertSame($service, $container->get(stdClass::class));
    }

    public function testInstanceReleasesPreviouslyRegisteredFactory(): void
    {
        $container = new Container();
        $captured = new stdClass();
        $reference = WeakReference::create($captured);

        $container->set(stdClass::class, static fn (): stdClass => $captured);
        $container->instance(stdClass::class, new stdClass());

        unset($captured);

        self::assertNull($reference->get());
    }

    public function testIsAPsrContainer(): void
    {
        self::assertInstanceOf(ContainerInterface::class, new Container());
    }

    public function testUnknownServiceThrowsPsrNotFoundException(): void
    {
        $container = new Container();

        $this->expectException(NotFoundExceptionInterface::class);

        $container->get(stdClass::class);
    }

    public function testCircularDependencyIsAContainerErrorAndNotANotFound(): void
    {
        $container = new Container();
        $container->set(
            stdClass::class,
            static fn (Container $c): stdClass => $c->get(stdClass::class),
        );

        // Written as a catch rather than expectException because the point is
        // what the exception is NOT. A wiring cycle is a container error; if it
        // also answered to NotFound, a plugin handling a missing service would
        // quietly swallow a bug in its own registration.
        try {
            $container->get(stdClass::class);
        } catch (ContainerExceptionInterface $exception) {
            self::assertNotInstanceOf(NotFoundExceptionInterface::class, $exception);

            return;
        }

        self::fail('Expected a PSR-11 container exception.');
    }
}
