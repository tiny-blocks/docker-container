<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Waits\Conditions\ExecutionCompleted;

final readonly class ContainerWaitForTime implements ContainerWaitAfterStarted
{
    use WaitForCondition;

    private function __construct(private ExecutionCompleted $condition)
    {
    }

    public static function untilContains(ExecutionCompleted $condition): ContainerWaitForTime
    {
        return new ContainerWaitForTime(condition: $condition);
    }

    public function waitAfter(ContainerStarted $containerStarted): void
    {
        $this->waitFor(condition: fn(): bool => $this->condition->isCompleteOn(containerStarted: $containerStarted));
    }
}
