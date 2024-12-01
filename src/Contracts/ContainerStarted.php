<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Contracts;

use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

/**
 * Defines the operations available for a Docker container that has been started.
 */
interface ContainerStarted
{
    public const int DEFAULT_TIMEOUT_IN_WHOLE_SECONDS = 300;

    /**
     * Returns the ID of the running container.
     *
     * @return string The container's ID.
     */
    public function getId(): string;

    /**
     * Returns the name of the running container.
     *
     * @return string The container's name.
     */
    public function getName(): string;

    /**
     * Returns the network address of the running container.
     *
     * @return Address The container's network address.
     */
    public function getAddress(): Address;

    /**
     * Returns the environment variables of the running container.
     *
     * @return EnvironmentVariables The environment variables of the container.
     */
    public function getEnvironmentVariables(): EnvironmentVariables;

    /**
     * Stops the running container.
     *
     * @param int $timeoutInWholeSeconds The maximum time in seconds to wait for the container to stop.
     *                                   Default is {@see DEFAULT_TIMEOUT_IN_WHOLE_SECONDS} seconds.
     * @return ExecutionCompleted The result of the stop command execution.
     * @throws DockerCommandExecutionFailed If the stop command fails to execute.
     */
    public function stop(int $timeoutInWholeSeconds = self::DEFAULT_TIMEOUT_IN_WHOLE_SECONDS): ExecutionCompleted;

    /**
     * Executes commands inside the running container after it has been started.
     *
     * @param array $commands The commands to execute inside the container.
     * @return ExecutionCompleted The result of the command execution.
     * @throws DockerCommandExecutionFailed If the command execution fails.
     */
    public function executeAfterStarted(array $commands): ExecutionCompleted;
}
