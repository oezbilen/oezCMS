<?php

declare(strict_types=1);

namespace OezCMS\Tests\Core;

use OezCMS\Core\Container;
use OezCMS\Core\ContainerException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ContainerTest extends TestCase
{
    public function testGetResolvesRegisteredFactory(): void
    {
        $container = new Container();
        $container->set(stdClass::class, static fn (): stdClass => new stdClass());

        self::assertInstanceOf(stdClass::class, $container->get(stdClass::class));
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
}
