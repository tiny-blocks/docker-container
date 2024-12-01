<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\Waits\ContainerWaitAfterStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitBeforeStarted;

/**
 * Defines operations for creating and managing Docker containers.
 */
interface DockerContainer
{
    /**
     * Creates an instance of a Docker container from an image and an optional name.
     *
     * @param string $image The name of the Docker image to be used.
     * @param string|null $name The optional name for the container.
     * @return DockerContainer The created container instance.
     */

    public static function from(string $image, ?string $name = null): DockerContainer;

    /**
     * Starts the container and runs the provided commands.
     *
     * Optionally, wait for a condition to be met after the container is started, using a
     * `ContainerWaitAfterStarted` instance.
     * This can be useful if you need to wait for specific events (e.g., log output or readiness) before proceeding.
     *
     * @param array $commands Commands to be executed after the container is started.
     * @param ContainerWaitAfterStarted|null $waitAfterStarted A `ContainerWaitAfterStarted` instance that defines the
     *                                                         condition to wait for after the container starts.
     *                                                         Default to null if no wait is required.
     * @return ContainerStarted The started container.
     * @throws DockerCommandExecutionFailed If the execution of the Docker command fails.
     */
    public function run(array $commands = [], ?ContainerWaitAfterStarted $waitAfterStarted = null): ContainerStarted;

    /**
     * Starts the container and runs the provided commands if it does not already exist.
     *
     * If the container doesn't exist, it will be created and started with the provided commands.
     * If the container already exists, no action will be taken.
     *
     * Optionally, wait for a condition to be met after the container is started, using a
     * `ContainerWaitAfterStarted` instance.
     * This can be useful if you need to wait for specific events (e.g., log output or readiness) before proceeding.
     *
     * @param array $commands Commands to be executed after the container is started if it doesn't
     *                         already exist.
     * @param ContainerWaitAfterStarted|null $waitAfterStarted A `ContainerWaitAfterStarted` instance that defines the
     *                                                         condition to wait for after the container starts.
     *                                                         Default to null if no wait is required.
     * @return ContainerStarted The started container.
     * @throws DockerCommandExecutionFailed If the execution of the Docker command fails.
     */
    public function runIfNotExists(
        array $commands = [],
        ?ContainerWaitAfterStarted $waitAfterStarted = null
    ): ContainerStarted;

    /**
     * Copies files or directories from the host to the container.
     *
     * @param string $pathOnHost The path on the host where the files/directories are located.
     * @param string $pathOnContainer The path on the container where the files/directories will be copied.
     * @return DockerContainer The container instance with the copied files.
     */
    public function copyToContainer(string $pathOnHost, string $pathOnContainer): DockerContainer;

    /**
     * Connects the container to a specific Docker network.
     *
     * @param string $name The name of the Docker network to connect the container to.
     * @return DockerContainer The container instance with the network configuration applied.
     */
    public function withNetwork(string $name): DockerContainer;

    /**
     * Maps a port from the host to the container.
     *
     * @param int $portOnHost The port on the host to be mapped.
     * @param int $portOnContainer The port on the container for the mapping.
     * @return DockerContainer The container instance with the mapped port.
     */
    public function withPortMapping(int $portOnHost, int $portOnContainer): DockerContainer;

    /**
     * Sets the wait condition to be applied before running the container.
     *
     * @param ContainerWaitBeforeStarted $wait The wait condition to apply before running the container.
     * @return DockerContainer The container instance with the wait condition before run.
     */
    public function withWaitBeforeRun(ContainerWaitBeforeStarted $wait): DockerContainer;

    /**
     * Sets whether the container should not be automatically removed after stopping.
     *
     * @return DockerContainer The container instance with the auto-remove setting disabled.
     */
    public function withoutAutoRemove(): DockerContainer;

    /**
     * Maps a volume from the host to the container.
     *
     * @param string $pathOnHost The path of the volume on the host.
     * @param string $pathOnContainer The path on the container where the volume will be mapped.
     * @return DockerContainer The container instance with the mapped volume.
     */
    public function withVolumeMapping(string $pathOnHost, string $pathOnContainer): DockerContainer;

    /**
     * Sets an environment variable for the container.
     *
     * @param string $key The key of the environment variable.
     * @param string $value The value of the environment variable.
     * @return DockerContainer The container instance with the environment variable configured.
     */
    public function withEnvironmentVariable(string $key, string $value): DockerContainer;
}
