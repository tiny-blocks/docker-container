<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;

final readonly class ContainerWaitForDependency implements ContainerWait
{
    private const int SECONDS = 1;

    private function __construct(private ContainerReady $condition)
    {
    }

    public static function untilReady(ContainerReady $condition): ContainerWaitForDependency
    {
        return new ContainerWaitForDependency(condition: $condition);
    }

    public function wait(): void
    {
        while (!$this->condition->isReady()) {
            sleep(self::SECONDS);
        }
    }
}
