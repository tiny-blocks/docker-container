<?php

declare(strict_types=1);

namespace Test\Unit\Waits;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\ContainerStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForTime;
use TinyBlocks\Time\Duration;

final class ContainerWaitForTimeTest extends TestCase
{
    public function testWaitAfterPausesForSpecifiedDuration(): void
    {
        /** @Given a wait-for-time of 1 second */
        $wait = ContainerWaitForTime::forDuration(duration: Duration::fromSeconds(seconds: 1));

        /** @And the moment the wait starts */
        $start = microtime(true);

        /** @When waiting after a container started */
        $wait->waitAfter(containerStarted: $this->createStub(ContainerStarted::class));

        /** @Then at least 0.9 seconds should have elapsed */
        self::assertGreaterThanOrEqual(0.9, (microtime(true) - $start));
    }

    public function testWaitBeforePausesForSpecifiedDuration(): void
    {
        /** @Given a wait-for-time of 1 second */
        $wait = ContainerWaitForTime::forDuration(duration: Duration::fromSeconds(seconds: 1));

        /** @And the moment the wait starts */
        $start = microtime(true);

        /** @When waiting before a container runs */
        $wait->waitBefore();

        /** @Then at least 0.9 seconds should have elapsed */
        self::assertGreaterThanOrEqual(0.9, (microtime(true) - $start));
    }

    public function testWaitForZeroSecondsReturnsImmediately(): void
    {
        /** @Given a wait-for-time of zero */
        $wait = ContainerWaitForTime::forDuration(duration: Duration::zero());

        /** @And the moment the wait starts */
        $start = microtime(true);

        /** @When waiting before a container runs */
        $wait->waitBefore();

        /** @Then the wait should return at once */
        self::assertLessThan(0.1, (microtime(true) - $start));
    }
}
