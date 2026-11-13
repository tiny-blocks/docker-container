<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;
use TinyBlocks\Time\Duration;

/**
 * Wait strategy that polls a readiness condition until it holds or the timeout elapses.
 *
 * <p>The condition is checked at least once, even when the timeout is zero, and again 250 milliseconds after each
 * check that fails. The time each check takes counts toward the timeout, which defaults to 30 seconds.</p>
 */
final readonly class ContainerWaitForDependency implements ContainerWaitBeforeStarted
{
    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;
    private const int DEFAULT_TIMEOUT_IN_SECONDS = 30;
    private const int POLL_INTERVAL_IN_MICROSECONDS = 250_000;

    private function __construct(private Duration $timeout, private ContainerReady $condition)
    {
    }

    /**
     * Creates a ContainerWaitForDependency that waits until the condition holds, within the default timeout.
     *
     * @param ContainerReady $condition The readiness condition to poll.
     * @return ContainerWaitForDependency The created wait strategy.
     */
    public static function untilReady(ContainerReady $condition): ContainerWaitForDependency
    {
        return new ContainerWaitForDependency(
            timeout: Duration::fromSeconds(seconds: self::DEFAULT_TIMEOUT_IN_SECONDS),
            condition: $condition
        );
    }

    public function waitBefore(): void
    {
        $deadline = (hrtime(true) + ($this->timeout->toSeconds() * self::NANOSECONDS_PER_SECOND));

        do {
            if ($this->condition->isReady()) {
                return;
            }

            usleep(self::POLL_INTERVAL_IN_MICROSECONDS);
        } while (hrtime(true) < $deadline);

        throw ContainerWaitTimeout::forReadiness(timeout: $this->timeout);
    }

    /**
     * Returns a copy of the ContainerWaitForDependency with the timeout replaced.
     *
     * @param Duration $timeout The longest time to wait for the condition to hold.
     * @return ContainerWaitForDependency The copy with the new timeout.
     */
    public function withTimeout(Duration $timeout): ContainerWaitForDependency
    {
        return clone($this, ['timeout' => $timeout]);
    }
}
