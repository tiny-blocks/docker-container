<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;

/**
 * Defines a wait strategy to be applied after a container has started.
 */
interface ContainerWaitAfterStarted extends ContainerWait
{
    /**
     * Waits after the container has started, blocking until the strategy is satisfied.
     *
     * @param ContainerStarted $containerStarted The started container instance.
     * @return void
     */
    public function waitAfter(ContainerStarted $containerStarted): void;
}
