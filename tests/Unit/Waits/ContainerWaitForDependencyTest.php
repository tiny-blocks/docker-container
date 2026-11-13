<?php

declare(strict_types=1);

namespace Test\Unit\Waits;

use PHPUnit\Framework\TestCase;
use Test\Unit\ClockMock;
use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;
use TinyBlocks\Time\Duration;

final class ContainerWaitForDependencyTest extends TestCase
{
    protected function setUp(): void
    {
        ClockMock::freeze();
    }

    protected function tearDown(): void
    {
        ClockMock::release();
    }

    public function testWaitBeforeWhenTimeoutIsZeroThenChecksTheConditionOnce(): void
    {
        /** @Given a condition that is immediately ready */
        $condition = $this->createMock(ContainerReady::class);

        /** @Then the condition should still be checked once */
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @When waiting with a zero timeout */
        ContainerWaitForDependency::untilReady(condition: $condition)
            ->withTimeout(timeout: Duration::zero())
            ->waitBefore();
    }

    public function testWaitBeforeWhenConditionHoldsLaterThenChecksUntilItHolds(): void
    {
        /** @Given a condition that becomes ready on the third check */
        $condition = $this->createMock(ContainerReady::class);

        /** @Then the condition should be checked three times */
        $condition->expects(self::exactly(3))
            ->method('isReady')
            ->willReturnOnConsecutiveCalls(false, false, true);

        /** @When waiting for the dependency within the default timeout */
        ContainerWaitForDependency::untilReady(condition: $condition)->waitBefore();
    }

    public function testWaitBeforeWhenConditionIsImmediatelyReadyThenChecksOnce(): void
    {
        /** @Given a condition that is immediately ready */
        $condition = $this->createMock(ContainerReady::class);

        /** @Then the condition should be checked exactly once */
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @When waiting for the dependency */
        ContainerWaitForDependency::untilReady(condition: $condition)->waitBefore();
    }

    public function testWaitBeforeWhenDeadlineIsReachedExactlyThenStopsChecking(): void
    {
        /** @Given a condition whose first check ends exactly at the deadline, once the sleep is added */
        $condition = $this->createMock(ContainerReady::class);

        /** @Then the condition should not be checked again */
        $condition->expects(self::once())->method('isReady')->willReturnCallback(
            static function (): bool {
                ClockMock::advanceBy(nanoseconds: 750_000_000);

                return false;
            }
        );

        /** @And a timeout exception should be thrown */
        $this->expectException(ContainerWaitTimeout::class);

        /** @When waiting for the dependency for one second */
        ContainerWaitForDependency::untilReady(condition: $condition)
            ->withTimeout(timeout: Duration::fromSeconds(seconds: 1))
            ->waitBefore();
    }

    public function testWaitBeforeWhenConditionHoldsLaterThenSleepsBetweenChecks(): void
    {
        /** @Given a condition that becomes ready on the third check */
        $condition = $this->createStub(ContainerReady::class);

        /** @And the condition fails twice before it holds */
        $condition->method('isReady')->willReturnOnConsecutiveCalls(false, false, true);

        /** @When waiting for the dependency */
        ContainerWaitForDependency::untilReady(condition: $condition)->waitBefore();

        /** @Then a quarter of a second should have been slept after each failed check */
        self::assertSame(0.5, ClockMock::elapsedInSeconds());
    }

    public function testWaitBeforeWhenConditionIsImmediatelyReadyThenDoesNotSleep(): void
    {
        /** @Given a condition that is immediately ready */
        $condition = $this->createStub(ContainerReady::class);

        /** @And the condition reports readiness on its first check */
        $condition->method('isReady')->willReturn(true);

        /** @When waiting for the dependency */
        ContainerWaitForDependency::untilReady(condition: $condition)->waitBefore();

        /** @Then no time should have been spent waiting */
        self::assertSame(0.0, ClockMock::elapsedInSeconds());
    }

    public function testWaitBeforeWhenConditionNeverHoldsThenContainerWaitTimeout(): void
    {
        /** @Given a condition that never becomes ready */
        $condition = $this->createStub(ContainerReady::class);

        /** @And the condition fails on every check */
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

    public function testWaitBeforeWhenConditionNeverHoldsThenWaitsExactlyTheTimeout(): void
    {
        /** @Given a condition that never becomes ready */
        $condition = $this->createStub(ContainerReady::class);

        /** @And the condition fails on every check */
        $condition->method('isReady')->willReturn(false);

        /** @And a wait bounded by two seconds */
        $wait = ContainerWaitForDependency::untilReady(condition: $condition)
            ->withTimeout(timeout: Duration::fromSeconds(seconds: 2));

        try {
            /** @When waiting for the dependency */
            $wait->waitBefore();
        } catch (ContainerWaitTimeout) {
            /** @Then exactly two seconds should have been spent waiting */
            self::assertSame(2.0, ClockMock::elapsedInSeconds());
        }
    }

    public function testWaitBeforeWhenConditionNeverHoldsThenChecksEveryQuarterSecond(): void
    {
        /** @Given a condition that never becomes ready and counts its checks */
        $condition = $this->createMock(ContainerReady::class);

        /** @Then the condition should be checked four times, once per quarter of the second */
        $condition->expects(self::exactly(4))->method('isReady')->willReturn(false);

        /** @And a timeout exception should be thrown */
        $this->expectException(ContainerWaitTimeout::class);

        /** @When waiting for the dependency for one second */
        ContainerWaitForDependency::untilReady(condition: $condition)
            ->withTimeout(timeout: Duration::fromSeconds(seconds: 1))
            ->waitBefore();
    }

    public function testWaitBeforeWhenOneNanosecondRemainsThenChecksTheConditionAgain(): void
    {
        /** @Given a condition whose first check ends one nanosecond before the deadline, once the sleep is added */
        $condition = $this->createMock(ContainerReady::class);

        /** @Then the condition should be checked a second time */
        $condition->expects(self::exactly(2))->method('isReady')->willReturnCallback(
            static function (): bool {
                ClockMock::advanceBy(nanoseconds: 749_999_999);

                return false;
            }
        );

        /** @And a timeout exception should be thrown */
        $this->expectException(ContainerWaitTimeout::class);

        /** @When waiting for the dependency for one second */
        ContainerWaitForDependency::untilReady(condition: $condition)
            ->withTimeout(timeout: Duration::fromSeconds(seconds: 1))
            ->waitBefore();
    }

    public function testWaitBeforeWhenChecksAreSlowThenTheirDurationCountsTowardTheTimeout(): void
    {
        /** @Given a condition that takes half a second to answer and never becomes ready */
        $condition = $this->createMock(ContainerReady::class);

        /** @Then the condition should be checked only twice within the second */
        $condition->expects(self::exactly(2))->method('isReady')->willReturnCallback(
            static function (): bool {
                ClockMock::advanceBy(nanoseconds: 500_000_000);

                return false;
            }
        );

        /** @And a timeout exception should be thrown */
        $this->expectException(ContainerWaitTimeout::class);

        /** @When waiting for the dependency for one second */
        ContainerWaitForDependency::untilReady(condition: $condition)
            ->withTimeout(timeout: Duration::fromSeconds(seconds: 1))
            ->waitBefore();
    }

    public function testWaitBeforeWhenTimeoutIsZeroAndConditionFailsThenContainerWaitTimeout(): void
    {
        /** @Given a condition that becomes ready only on the second check */
        $condition = $this->createStub(ContainerReady::class);

        /** @And the condition fails its first check */
        $condition->method('isReady')->willReturnOnConsecutiveCalls(false, true);

        /** @And a wait with a zero timeout, which allows a single check */
        $wait = ContainerWaitForDependency::untilReady(condition: $condition)->withTimeout(timeout: Duration::zero());

        /** @Then a timeout exception should be thrown */
        $this->expectException(ContainerWaitTimeout::class);

        /** @When waiting for the dependency */
        $wait->waitBefore();
    }
}
