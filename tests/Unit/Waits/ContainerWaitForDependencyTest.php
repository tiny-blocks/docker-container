<?php

declare(strict_types=1);

namespace Test\Unit\Waits;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;
use TinyBlocks\Time\Duration;

final class ContainerWaitForDependencyTest extends TestCase
{
    public function testWaitBeforeWhenTimeoutIsZeroThenChecksTheConditionOnce(): void
    {
        /** @Given a condition that is immediately ready */
        $condition = $this->createMock(ContainerReady::class);
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @When waiting with a zero timeout */
        ContainerWaitForDependency::untilReady(condition: $condition)
            ->withTimeout(timeout: Duration::zero())
            ->waitBefore();

        /** @Then the condition should still have been checked once */
        self::assertTrue(true);
    }

    public function testWaitBeforeWhenConditionHoldsLaterThenChecksUntilItHolds(): void
    {
        /** @Given a condition that becomes ready on the third check */
        $condition = $this->createMock(ContainerReady::class);
        $condition->expects(self::exactly(3))
            ->method('isReady')
            ->willReturnOnConsecutiveCalls(false, false, true);

        /** @When waiting for the dependency within the default timeout */
        ContainerWaitForDependency::untilReady(condition: $condition)->waitBefore();

        /** @Then the condition should have been checked three times */
        self::assertTrue(true);
    }

    public function testWaitBeforeWhenConditionIsImmediatelyReadyThenChecksOnce(): void
    {
        /** @Given a condition that is immediately ready */
        $condition = $this->createMock(ContainerReady::class);
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @When waiting for the dependency */
        ContainerWaitForDependency::untilReady(condition: $condition)->waitBefore();

        /** @Then the condition should have been checked exactly once */
        self::assertTrue(true);
    }

    public function testWaitBeforeWhenConditionHoldsLaterThenSleepsBetweenChecks(): void
    {
        /** @Given the moment the wait starts */
        $start = microtime(true);

        /** @And a condition that holds only after the first poll interval has elapsed */
        $condition = $this->createStub(ContainerReady::class);
        $condition->method('isReady')->willReturnCallback(
            static fn(): bool => (microtime(true) - $start) >= 0.2
        );

        /** @When waiting for the dependency */
        ContainerWaitForDependency::untilReady(condition: $condition)->waitBefore();

        /** @Then the wait should have slept a full poll interval before observing readiness */
        self::assertGreaterThanOrEqual(0.25, (microtime(true) - $start));
    }

    public function testWaitBeforeWhenConditionNeverHoldsThenContainerWaitTimeout(): void
    {
        /** @Given a condition that never becomes ready */
        $condition = $this->createStub(ContainerReady::class);
        $condition->method('isReady')->willReturn(false);

        /** @And a wait bounded by one second */
        $wait = ContainerWaitForDependency::untilReady(condition: $condition)
            ->withTimeout(timeout: Duration::fromSeconds(seconds: 1));

        /** @Then a timeout exception carrying the timeout should be thrown */
        $this->expectException(ContainerWaitTimeout::class);
        $this->expectExceptionMessage('Container readiness check timed out after <1> seconds.');

        /** @When waiting for the dependency */
        $wait->waitBefore();
    }

    public function testWaitBeforeWhenConditionNeverHoldsThenChecksEveryQuarterSecond(): void
    {
        /** @Given a condition that never becomes ready and counts its checks */
        $condition = $this->createMock(ContainerReady::class);
        $condition->expects(self::exactly(4))->method('isReady')->willReturn(false);

        /** @And a wait bounded by one second */
        $wait = ContainerWaitForDependency::untilReady(condition: $condition)
            ->withTimeout(timeout: Duration::fromSeconds(seconds: 1));

        /** @Then a timeout exception should be thrown once the four checks of the second are spent */
        $this->expectException(ContainerWaitTimeout::class);

        /** @When waiting for the dependency */
        $wait->waitBefore();
    }

    public function testWaitBeforeWhenTimeoutIsZeroAndConditionFailsThenContainerWaitTimeout(): void
    {
        /** @Given a condition that becomes ready only on the second check */
        $condition = $this->createStub(ContainerReady::class);
        $condition->method('isReady')->willReturnOnConsecutiveCalls(false, true);

        /** @And a wait with a zero timeout, which allows a single check */
        $wait = ContainerWaitForDependency::untilReady(condition: $condition)->withTimeout(timeout: Duration::zero());

        /** @Then a timeout exception should be thrown */
        $this->expectException(ContainerWaitTimeout::class);

        /** @When waiting for the dependency */
        $wait->waitBefore();
    }
}
