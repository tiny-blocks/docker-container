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
        $start = microtime(true);
        $wait->waitBefore();
        $elapsed = microtime(true) - $start;

        /** @Then at least 0.9 seconds should have elapsed */
        self::assertGreaterThanOrEqual(minimum: 0.9, actual: $elapsed);
    }

    public function testWaitAfterPausesForSpecifiedDuration(): void
    {
        /** @Given a wait-for-time of 1 second */
        $wait = ContainerWaitForTime::forSeconds(seconds: 1);

        /** @And a container started stub */
        $containerStarted = $this->createStub(ContainerStarted::class);

        /** @When waiting after */
        $start = microtime(true);
        $wait->waitAfter(containerStarted: $containerStarted);
        $elapsed = microtime(true) - $start;

        /** @Then at least 0.9 seconds should have elapsed */
        self::assertGreaterThanOrEqual(minimum: 0.9, actual: $elapsed);
    }

    public function testWaitForZeroSecondsReturnsImmediately(): void
    {
        /** @Given a wait-for-time of 0 seconds */
        $wait = ContainerWaitForTime::forSeconds(seconds: 0);

        /** @When waiting before */
        $start = microtime(true);
        $wait->waitBefore();
        $elapsed = microtime(true) - $start;

        /** @Then the wait should complete almost instantly */
        self::assertLessThan(maximum: 0.1, actual: $elapsed);
    }
}
