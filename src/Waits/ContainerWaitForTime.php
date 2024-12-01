<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;

final readonly class ContainerWaitForTime implements ContainerWaitBeforeStarted, ContainerWaitAfterStarted
{
    private function __construct(private int $seconds)
    {
    }

    public static function forSeconds(int $seconds): ContainerWaitForTime
    {
        return new ContainerWaitForTime(seconds: $seconds);
    }

    public function waitBefore(): void
    {
        sleep($this->seconds);
    }

    public function waitAfter(ContainerStarted $containerStarted): void
    {
        sleep($this->seconds);
    }
}
