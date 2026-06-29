<?php

declare(strict_types=1);

namespace Test\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TinyBlocks\DockerContainer\EnvironmentFlag;

final class EnvironmentFlagTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('TINY_BLOCKS_DOCKER_FLAG');
    }

    public function testEnabledWhenVariableIsOneThenPredicateReturnsTrue(): void
    {
        /** @Given an environment variable set to one */
        putenv('TINY_BLOCKS_DOCKER_FLAG=1');

        /** @When the predicate built for that variable is evaluated */
        $enabled = EnvironmentFlag::enabled(name: 'TINY_BLOCKS_DOCKER_FLAG')();

        /** @Then the predicate should hold */
        self::assertTrue($enabled);
    }

    public function testEnabledWhenVariableIsZeroThenPredicateReturnsFalse(): void
    {
        /** @Given an environment variable set to zero */
        putenv('TINY_BLOCKS_DOCKER_FLAG=0');

        /** @When the predicate built for that variable is evaluated */
        $enabled = EnvironmentFlag::enabled(name: 'TINY_BLOCKS_DOCKER_FLAG')();

        /** @Then the predicate should not hold */
        self::assertFalse($enabled);
    }

    public function testEnabledWhenVariableIsUnsetThenPredicateReturnsFalse(): void
    {
        /** @Given the environment variable is not set */
        putenv('TINY_BLOCKS_DOCKER_FLAG');

        /** @When the predicate built for that variable is evaluated */
        $enabled = EnvironmentFlag::enabled(name: 'TINY_BLOCKS_DOCKER_FLAG')();

        /** @Then the predicate should not hold */
        self::assertFalse($enabled);
    }

    public function testConstructWhenInvokedThroughReflectionThenInstanceIsCreated(): void
    {
        /** @Given a reflection over the environment flag surface */
        $reflection = new ReflectionClass(EnvironmentFlag::class);

        /** @And an instance built without invoking the constructor */
        $instance = $reflection->newInstanceWithoutConstructor();

        /** @When the private constructor is invoked through reflection */
        $reflection->getMethod('__construct')->invoke($instance);

        /** @Then the environment flag instance is created */
        self::assertInstanceOf(EnvironmentFlag::class, $instance);
    }
}
