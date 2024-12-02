<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal;

use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Commands\Command;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Container;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

/**
 * Handles Docker command execution.
 */
interface CommandHandler
{
    /**
     * Executes a Docker run command.
     *
     * @param DockerRun $dockerRun The command to run the container.
     * @return Container The created container.
     * @throws DockerCommandExecutionFailed If the command execution fails.
     */
    public function run(DockerRun $dockerRun): Container;

    /**
     * Finds a container based on the provided criteria.
     *
     * @param DockerList $dockerList The criteria to find the container.
     * @return Container The found container or a new one if not found.
     * @throws DockerCommandExecutionFailed If the command execution fails.
     */
    public function findBy(DockerList $dockerList): Container;

    /**
     * Executes a generic Docker command.
     *
     * @param Command $command The command to execute.
     * @return ExecutionCompleted The result of the execution.
     * @throws DockerCommandExecutionFailed If the command execution fails.
     */
    public function execute(Command $command): ExecutionCompleted;
}
