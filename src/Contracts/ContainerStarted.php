<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Contracts;

use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

/**
 * Represents a Docker container that has been started and is running.
 */
interface ContainerStarted
{
    /**
     * Default timeout in whole seconds used when stopping the container.
     */
    public const int DEFAULT_TIMEOUT_IN_WHOLE_SECONDS = 300;

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
     * Returns the environment variables configured in the container.
     *
     * @return EnvironmentVariables The container's environment variables.
     */
    public function getEnvironmentVariables(): EnvironmentVariables;

    /**
     * Stops the running container.
     *
     * @param int $timeoutInWholeSeconds The maximum time in seconds to wait for the container to stop.
     * @return ExecutionCompleted The result of the stop command execution.
     * @throws DockerCommandExecutionFailed If the stop command fails.
     */
    public function stop(int $timeoutInWholeSeconds = self::DEFAULT_TIMEOUT_IN_WHOLE_SECONDS): ExecutionCompleted;

    /**
     * Executes commands inside the running container.
     *
     * @param array<int, string> $commands The commands to execute inside the container.
     * @return ExecutionCompleted The result of the command execution.
     * @throws DockerCommandExecutionFailed If the command execution fails.
     */
    public function executeAfterStarted(array $commands): ExecutionCompleted;
}
