<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\CommandHandler;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Commands\Command;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

/**
 * Defines the contract for handling Docker container commands such as run, find, and execute.
 */
interface CommandHandler
{
    /**
     * Runs a new Docker container using the given run command.
     *
     * @param DockerRun $dockerRun The Docker run command with container configuration.
     * @return ContainerStarted The started container instance.
     * @throws DockerCommandExecutionFailed If the run command fails.
     */
    public function run(DockerRun $dockerRun): ContainerStarted;

    /**
     * Finds an existing container matching the given definition.
     *
     * @param ContainerDefinition $definition The container definition to search for.
     * @return ContainerStarted|null The existing container, or null if not found.
     * @throws DockerCommandExecutionFailed If the search command fails.
     */
    public function findBy(ContainerDefinition $definition): ?ContainerStarted;

    /**
     * Executes a Docker command and returns the result.
     *
     * @param Command $command The Docker command to execute.
     * @return ExecutionCompleted The result of the command execution.
     * @throws DockerCommandExecutionFailed If the command execution fails.
     */
    public function execute(Command $command): ExecutionCompleted;
}
