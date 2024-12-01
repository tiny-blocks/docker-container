<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;

final readonly class ContainerWaitForDependency implements ContainerWaitBeforeStarted
{
    use WaitForCondition;

    private function __construct(private ContainerReady $condition)
    {
    }

    public static function untilReady(ContainerReady $condition): ContainerWaitForDependency
    {
        return new ContainerWaitForDependency(condition: $condition);
    }

    public function waitBefore(): void
    {
        $this->waitFor(condition: fn(): bool => $this->condition->isReady());
    }
}
