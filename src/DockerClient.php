<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;

/**
 * Executes the Docker commands the library issues, the single boundary between the library and the Docker engine.
 *
 * <p>Every container factory defaults to a client that invokes the local <code>docker</code> executable. A custom
 * client replaces it, for example to route the commands to another Docker-compatible engine, or to stand in for
 * the engine in the unit tests of code that builds containers.</p>
 */
interface DockerClient
{
    /**
     * Executes a Docker command within the default timeout of the client and returns its outcome.
     *
     * @param DockerCommand $command The Docker command to execute.
     * @return ExecutionCompleted The outcome of the command.
     * @throws DockerCommandExecutionFailed If the command cannot run or exceeds the default timeout.
     */
    public function execute(DockerCommand $command): ExecutionCompleted;

    /**
     * Executes a Docker command within the timeout the command carries and returns its outcome.
     *
     * @param DockerCommandWithTimeout $command The Docker command to execute.
     * @return ExecutionCompleted The outcome of the command.
     * @throws DockerCommandExecutionFailed If the command cannot run or exceeds its timeout.
     */
    public function executeWithTimeout(DockerCommandWithTimeout $command): ExecutionCompleted;
}
