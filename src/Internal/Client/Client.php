<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Client;

use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Commands\Command;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

/**
 * Defines the contract for executing Docker commands via the system process.
 */
interface Client
{
    /**
     * Executes a Docker command and returns the result.
     *
     * @param Command $command The Docker command to execute.
     * @return ExecutionCompleted The result of the command execution.
     * @throws DockerCommandExecutionFailed If the command execution fails.
     */
    public function execute(Command $command): ExecutionCompleted;
}
