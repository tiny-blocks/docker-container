<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits\Conditions;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

/**
 * Defines the strategy for checking if an execution in a Docker container has been completed.
 */
interface ExecutionCompleted
{
    /**
     * Checks if the execution has been completed on the specified container.
     *
     * @param ContainerStarted $containerStarted The container where the execution completion will be checked.
     * @return bool Returns true if the execution has been completed, false otherwise.
     * @throws DockerCommandExecutionFailed If the command to check execution completion fails.
     */
    public function isCompleteOn(ContainerStarted $containerStarted): bool;
}
