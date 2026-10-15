<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\Time\Duration;

/**
 * Represents a Docker container that has been started and is running.
 *
 * <p>A container this process started on its own is a {@see DisposableContainer}, which it may remove. A container
 * shared among processes is a {@see SharedContainer}, which tells whether it was reused.</p>
 */
interface ContainerStarted
{
    /**
     * Stops the running container gracefully.
     *
     * <p>Docker forces the stop with SIGKILL once the grace period elapses.</p>
     *
     * @param Duration $gracePeriod The time to wait before forcing the stop.
     * @return ExecutionCompleted The result of the stop command execution.
     * @throws DockerCommandExecutionFailed If the stop command cannot run.
     */
    public function stop(Duration $gracePeriod): ExecutionCompleted;

    /**
     * Returns the unique identifier of the container.
     *
     * @return string The container ID.
     */
    public function getId(): string;

    /**
     * Returns the name assigned to the container.
     *
     * @return string The container name.
     */
    public function getName(): string;

    /**
     * Returns the network address of the container.
     *
     * @return Address The container's network address.
     */
    public function getAddress(): Address;

    /**
     * Executes commands inside the running container.
     *
     * @param array<int, string> $commands The commands to execute inside the container.
     * @return ExecutionCompleted The result of the command execution.
     * @throws DockerCommandExecutionFailed If the command execution cannot run.
     */
    public function executeAfterStarted(array $commands): ExecutionCompleted;

    /**
     * Returns the environment variables configured in the container.
     *
     * @return EnvironmentVariables The container's environment variables.
     */
    public function getEnvironmentVariables(): EnvironmentVariables;
}
