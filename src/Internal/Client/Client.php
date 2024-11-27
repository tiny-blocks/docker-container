<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Client;

use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Commands\Command;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

/**
 * Defines the contract for a Docker client that can execute commands inside a container.
 */
interface Client
{
    /**
     * Executes a Docker command and returns the result.
     *
     * @param Command $command The command to be executed inside the Docker container.
     * @return ExecutionCompleted The result of executing the command, including any output or errors.
     * @throws DockerCommandExecutionFailed If the command execution fails in the Docker environment.
     */
    public function execute(Command $command): ExecutionCompleted;
}
