<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits\Conditions;

use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

/**
 * Defines the strategy for checking if a Docker container is ready.
 */
interface ContainerReady
{
    /**
     * Checks if the container is ready based on its specific conditions.
     *
     * @return bool Returns true if the container is ready, false otherwise.
     * @throws DockerCommandExecutionFailed If the command to check readiness fails.
     */
    public function isReady(): bool;
}
