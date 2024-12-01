<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

/**
 * Defines the strategy for waiting for a condition to be met before a Docker container has started.
 */
interface ContainerWaitBeforeStarted
{
    /**
     * Waits for a condition to be met before the container starts.
     *
     * @return void
     * @throws DockerCommandExecutionFailed If the command to check the condition before the container start fails.
     */
    public function waitBefore(): void;
}
