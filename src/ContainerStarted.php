<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

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
     * Stops the running container gracefully.
     *
     * @param int $timeoutInWholeSeconds The maximum time in seconds to wait for the container to stop.
     * @return ExecutionCompleted The result of the stop command execution.
     * @throws DockerCommandExecutionFailed If the stop command fails.
     */
    public function stop(int $timeoutInWholeSeconds = self::DEFAULT_TIMEOUT_IN_WHOLE_SECONDS): ExecutionCompleted;

    /**
     * Returns the unique identifier of the container.
     *
     * @return string The container ID.
     */
    public function getId(): string;

    /**
     * Forcefully removes the container and its anonymous volumes, then prunes
     * unused networks created by the library.
     */
    public function remove(): void;

    /**
     * Returns the name assigned to the container.
     *
     * @return string The container name.
     */
    public function getName(): string;

    /**
     * Tells whether the container was reused from an already-running instance.
     *
     * @return bool True when an existing container was reused, false when a new one was started.
     */
    public function wasReused(): bool;

    /**
     * Returns the network address of the container.
     *
     * @return Address The container's network address.
     */
    public function getAddress(): Address;

    /**
     * Registers the container to be removed when the PHP process exits.
     */
    public function stopOnShutdown(): void;

    /**
     * Executes commands inside the running container.
     *
     * @param array<int, string> $commands The commands to execute inside the container.
     * @return ExecutionCompleted The result of the command execution.
     * @throws DockerCommandExecutionFailed If the command execution fails.
     */
    public function executeAfterStarted(array $commands): ExecutionCompleted;

    /**
     * Returns the environment variables configured in the container.
     *
     * @return EnvironmentVariables The container's environment variables.
     */
    public function getEnvironmentVariables(): EnvironmentVariables;
}
