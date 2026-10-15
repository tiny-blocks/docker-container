<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;

/**
 * Defines a wait strategy to be applied before a container runs.
 */
interface ContainerWaitBeforeStarted
{
    /**
     * Waits before the container runs, blocking until the strategy is satisfied.
     *
     * @throws ContainerWaitTimeout If the strategy is not satisfied within its timeout.
     */
    public function waitBefore(): void;
}
