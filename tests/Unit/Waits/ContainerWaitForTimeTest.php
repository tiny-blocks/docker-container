<?php

declare(strict_types=1);

namespace Test\Unit\Waits;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForTime;

final class ContainerWaitForTimeTest extends TestCase
{
    public function testWaitBeforePausesForSpecifiedDuration(): void
    {
        /** @Given a wait-for-time of 1 second */
        $wait = ContainerWaitForTime::forSeconds(seconds: 1);

        /** @When waiting before */
        $start = microtime(as_float: true);
        $wait->waitBefore();
        $elapsed = microtime(as_float: true) - $start;

        /** @Then at least 0.9 seconds should have elapsed */
        self::assertGreaterThanOrEqual(0.9, $elapsed);
    }

    public function testWaitAfterPausesForSpecifiedDuration(): void
    {
        /** @Given a wait-for-time of 1 second */
        $wait = ContainerWaitForTime::forSeconds(seconds: 1);

        /** @And a mock container started */
        $containerStarted = $this->createMock(ContainerStarted::class);

        /** @When waiting after */
        $start = microtime(as_float: true);
        $wait->waitAfter(containerStarted: $containerStarted);
        $elapsed = microtime(as_float: true) - $start;

        /** @Then at least 0.9 seconds should have elapsed */
        self::assertGreaterThanOrEqual(0.9, $elapsed);
    }

    public function testWaitForZeroSecondsReturnsImmediately(): void
    {
        /** @Given a wait-for-time of 0 seconds */
        $wait = ContainerWaitForTime::forSeconds(seconds: 0);

        /** @When waiting before */
        $start = microtime(as_float: true);
        $wait->waitBefore();
        $elapsed = microtime(as_float: true) - $start;

        /** @Then the wait should complete almost instantly */
        self::assertLessThan(0.1, $elapsed);
    }
}
