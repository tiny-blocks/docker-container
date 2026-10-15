<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\ContainerStarted;
use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;

/**
 * Defines a wait strategy to be applied after a container has started.
 */
interface ContainerWaitAfterStarted
{
    /**
     * Waits after the container has started, blocking until the strategy is satisfied.
     *
     * @param ContainerStarted $containerStarted The started container instance.
     * @throws ContainerWaitTimeout If the strategy is not satisfied within its timeout.
     */
    public function waitAfter(ContainerStarted $containerStarted): void;
}
