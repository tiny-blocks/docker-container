<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;

final readonly class ContainerWaitForDependency implements ContainerWaitBeforeStarted
{
    private function __construct(private ContainerReady $condition)
    {
    }

    public static function untilReady(ContainerReady $condition): ContainerWaitForDependency
    {
        return new ContainerWaitForDependency(condition: $condition);
    }

    public function waitBefore(): void
    {
        while (!$this->condition->isReady()) {
            sleep(self::WAIT_TIME_IN_WHOLE_SECONDS);
        }
    }
}
