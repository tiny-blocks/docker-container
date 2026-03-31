<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

/**
 * Defines a wait strategy to be applied before a container runs.
 */
interface ContainerWaitBeforeStarted extends ContainerWait
{
    /**
     * Waits before the container runs, blocking until the strategy is satisfied.
     *
     * @return void
     */
    public function waitBefore(): void;
}
