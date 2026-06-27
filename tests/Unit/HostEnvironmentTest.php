<?php

declare(strict_types=1);

namespace Test\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TinyBlocks\DockerContainer\Internal\Containers\HostEnvironment;

final class HostEnvironmentTest extends TestCase
{
    public function testConstructWhenInvokedThroughReflectionThenInstanceIsCreated(): void
    {
        /** @Given a reflection over the host environment surface */
        $reflection = new ReflectionClass(HostEnvironment::class);

        /** @And an instance built without invoking the constructor */
        $instance = $reflection->newInstanceWithoutConstructor();

        /** @When the private constructor is invoked through reflection */
        $reflection->getMethod('__construct')->invoke($instance);

        /** @Then the host environment instance is created */
        self::assertInstanceOf(HostEnvironment::class, $instance);
    }
}
