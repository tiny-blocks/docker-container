<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

/**
 * Defines the strategy for waiting for a condition to be met after a Docker container has started.
 */
interface ContainerWaitAfterStarted
{
    /**
     * Waits for a condition to be met after the container has started.
     *
     * @param ContainerStarted $containerStarted The container after it has been started, on which the
     *                                            condition will be checked.
     * @return void
     * @throws DockerCommandExecutionFailed If the command to check the condition after the container start fails.
     */
    public function waitAfter(ContainerStarted $containerStarted): void;
}
