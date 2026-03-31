<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;

final readonly class ContainerWaitForTime implements ContainerWaitBeforeStarted, ContainerWaitAfterStarted
{
    private const int MICROSECONDS_PER_SECOND = 1_000_000;

    private function __construct(private int $seconds)
    {
    }

    public static function forSeconds(int $seconds): ContainerWaitForTime
    {
        return new ContainerWaitForTime(seconds: $seconds);
    }

    public function waitBefore(): void
    {
        usleep($this->seconds * self::MICROSECONDS_PER_SECOND);
    }

    public function waitAfter(ContainerStarted $containerStarted): void
    {
        usleep($this->seconds * self::MICROSECONDS_PER_SECOND);
    }
}
