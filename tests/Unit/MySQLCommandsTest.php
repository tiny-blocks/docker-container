<?php

declare(strict_types=1);

namespace Test\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL\MySQLCommands;

final class MySQLCommandsTest extends TestCase
{
    public function testConstructWhenInvokedThroughReflectionThenInstanceIsCreated(): void
    {
        /** @Given a reflection over the MySQL commands surface */
        $reflection = new ReflectionClass(MySQLCommands::class);

        /** @And an instance built without invoking the constructor */
        $instance = $reflection->newInstanceWithoutConstructor();

        /** @When the private constructor is invoked through reflection */
        $reflection->getMethod('__construct')->invoke($instance);

        /** @Then the MySQL commands instance is created */
        self::assertInstanceOf(MySQLCommands::class, $instance);
    }
}
