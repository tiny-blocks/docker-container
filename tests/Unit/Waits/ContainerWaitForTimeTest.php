<?php

declare(strict_types=1);

namespace Test\Unit\Waits;

use PHPUnit\Framework\TestCase;
use Test\Unit\ClockMock;
use TinyBlocks\DockerContainer\ContainerStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForTime;
use TinyBlocks\Time\Duration;

final class ContainerWaitForTimeTest extends TestCase
{
    protected function setUp(): void
    {
        ClockMock::freeze();
    }

    protected function tearDown(): void
    {
        ClockMock::release();
    }

    public function testWaitBeforeWhenDurationIsZeroThenReturnsAtOnce(): void
    {
        /** @Given a wait-for-time of zero */
        $wait = ContainerWaitForTime::forDuration(duration: Duration::zero());

        /** @When waiting before a container runs */
        $wait->waitBefore();

        /** @Then no time should have been spent waiting */
        self::assertSame(0.0, ClockMock::elapsedInSeconds());
    }

    public function testWaitAfterWhenDurationIsGivenThenPausesForThatDuration(): void
    {
        /** @Given a wait-for-time of three seconds */
        $wait = ContainerWaitForTime::forDuration(duration: Duration::fromSeconds(seconds: 3));

        /** @When waiting after a container started */
        $wait->waitAfter(containerStarted: $this->createStub(ContainerStarted::class));

        /** @Then exactly three seconds should have been spent waiting */
        self::assertSame(3.0, ClockMock::elapsedInSeconds());
    }

    public function testWaitBeforeWhenDurationIsGivenThenPausesForThatDuration(): void
    {
        /** @Given a wait-for-time of three seconds */
        $wait = ContainerWaitForTime::forDuration(duration: Duration::fromSeconds(seconds: 3));

        /** @When waiting before a container runs */
        $wait->waitBefore();

        /** @Then exactly three seconds should have been spent waiting */
        self::assertSame(3.0, ClockMock::elapsedInSeconds());
    }
}
