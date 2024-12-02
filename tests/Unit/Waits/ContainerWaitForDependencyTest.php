<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;

final class ContainerWaitForDependencyTest extends TestCase
{
    public function testWaitBefore(): void
    {
        /** @Given I have a condition */
        $condition = $this->createMock(ContainerReady::class);

        /** @And the condition does not initially indicate the dependency is ready */
        $condition->expects(self::exactly(2))
            ->method('isReady')
            ->willReturnOnConsecutiveCalls(false, true);

        /** @When I wait until the condition is satisfied */
        $wait = ContainerWaitForDependency::untilReady(condition: $condition);
        $wait->waitBefore();

        /** @Then the condition should eventually return true, indicating the dependency is ready */
        self::assertTrue(true);
    }

    public function testWaitBeforeWhenConditionIsReady(): void
    {
        /** @Given I have a condition */
        $condition = $this->createMock(ContainerReady::class);

        /** @And the condition initially indicates the dependency is ready */
        $condition->expects(self::once())
            ->method('isReady')
            ->willReturn(true);

        /** @When I wait until the condition is satisfied */
        $wait = ContainerWaitForDependency::untilReady(condition: $condition);
        $wait->waitBefore();

        /** @Then the condition should return true immediately, indicating the dependency is ready */
        self::assertTrue(true);
    }
}
