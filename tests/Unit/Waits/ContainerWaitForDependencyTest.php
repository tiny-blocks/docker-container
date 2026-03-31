<?php

declare(strict_types=1);

namespace Test\Unit\Waits;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Internal\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;

final class ContainerWaitForDependencyTest extends TestCase
{
    public function testWaitBeforeWhenConditionIsImmediatelyReady(): void
    {
        /** @Given a condition that is immediately ready */
        $condition = $this->createMock(ContainerReady::class);
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @When waiting for the dependency */
        $wait = ContainerWaitForDependency::untilReady(condition: $condition);
        $wait->waitBefore();

        /** @Then the condition should have been checked exactly once */
        self::assertTrue(true);
    }

    public function testWaitBeforeRetriesUntilReady(): void
    {
        /** @Given a condition that becomes ready after two retries */
        $condition = $this->createMock(ContainerReady::class);
        $condition->expects(self::exactly(3))
            ->method('isReady')
            ->willReturnOnConsecutiveCalls(false, false, true);

        /** @When waiting for the dependency with a generous timeout */
        $wait = ContainerWaitForDependency::untilReady(
            condition: $condition,
            timeoutInSeconds: 10,
            pollIntervalInMicroseconds: 1_000
        );
        $wait->waitBefore();

        /** @Then the condition should have been checked three times */
        self::assertTrue(true);
    }

    public function testExceptionWhenWaitTimesOut(): void
    {
        /** @Given a condition that never becomes ready */
        $condition = $this->createMock(ContainerReady::class);
        $condition->method('isReady')->willReturn(false);

        /** @Then a ContainerWaitTimeout exception should be thrown */
        $this->expectException(ContainerWaitTimeout::class);
        $this->expectExceptionMessage('Container readiness check timed out after <1> seconds.');

        /** @When waiting with a short timeout */
        $wait = ContainerWaitForDependency::untilReady(
            condition: $condition,
            timeoutInSeconds: 1,
            pollIntervalInMicroseconds: 50_000
        );
        $wait->waitBefore();
    }

    public function testCustomPollIntervalIsRespected(): void
    {
        /** @Given a condition that becomes ready after some retries */
        $callCount = 0;
        $condition = $this->createMock(ContainerReady::class);
        $condition->method('isReady')->willReturnCallback(function () use (&$callCount): bool {
            $callCount++;
            return $callCount >= 3;
        });

        /** @When waiting with a very fast poll interval */
        $start = microtime(as_float: true);
        $wait = ContainerWaitForDependency::untilReady(
            condition: $condition,
            timeoutInSeconds: 5,
            pollIntervalInMicroseconds: 10_000
        );
        $wait->waitBefore();
        $elapsed = microtime(as_float: true) - $start;

        /** @Then the wait should complete quickly (well under 1 second) */
        self::assertLessThan(1.0, $elapsed);
        self::assertSame(3, $callCount);
    }
}
