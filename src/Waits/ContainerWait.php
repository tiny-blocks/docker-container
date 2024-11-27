<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

/**
 * Defines a contract for waiting mechanisms that ensure a Docker container meets a specific condition.
 */
interface ContainerWait
{
    /**
     * Blocks the execution until the container satisfies the condition defined by the implementing class.
     *
     * @throws DockerCommandExecutionFailed If the waiting process or the command execution fails.
     */
    public function wait(): void;
}
