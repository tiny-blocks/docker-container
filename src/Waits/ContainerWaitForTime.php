<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\ContainerStarted;
use TinyBlocks\Time\Duration;

/**
 * Wait strategy that pauses for a fixed duration, before a container runs or after it starts.
 */
final readonly class ContainerWaitForTime implements ContainerWaitBeforeStarted, ContainerWaitAfterStarted
{
    private const int MICROSECONDS_PER_SECOND = 1_000_000;

    private function __construct(private Duration $duration)
    {
    }

    /**
     * Creates a ContainerWaitForTime that pauses for the given duration.
     *
     * @param Duration $duration The time to pause.
     * @return ContainerWaitForTime The created wait strategy.
     */
    public static function forDuration(Duration $duration): ContainerWaitForTime
    {
        return new ContainerWaitForTime(duration: $duration);
    }

    public function waitAfter(ContainerStarted $containerStarted): void
    {
        $this->waitBefore();
    }

    public function waitBefore(): void
    {
        usleep($this->duration->toSeconds() * self::MICROSECONDS_PER_SECOND);
    }
}
