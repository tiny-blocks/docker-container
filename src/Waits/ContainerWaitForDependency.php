<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;
use TinyBlocks\Time\Duration;

/**
 * Wait strategy that polls a readiness condition until it holds or the timeout elapses.
 *
 * <p>The condition is checked every 250 milliseconds and at least once, even when the timeout is zero. The timeout
 * defaults to 30 seconds.</p>
 */
final readonly class ContainerWaitForDependency implements ContainerWaitBeforeStarted
{
    private const int MICROSECONDS_PER_SECOND = 1_000_000;
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
        $budgetInMicroseconds = ($this->timeout->toSeconds() * self::MICROSECONDS_PER_SECOND);
        $attempts = max(1, intdiv($budgetInMicroseconds, self::POLL_INTERVAL_IN_MICROSECONDS));

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            if ($this->condition->isReady()) {
                return;
            }

            usleep(self::POLL_INTERVAL_IN_MICROSECONDS);
        }

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
        return new ContainerWaitForDependency(timeout: $timeout, condition: $this->condition);
    }
}
