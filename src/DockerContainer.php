<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\Waits\ContainerWait;

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
     * @param array $commandsOnRun Commands to be executed after the container is started.
     * @return ContainerStarted The started container.
     * @throws DockerCommandExecutionFailed If the execution of the Docker command fails.
     */
    public function run(array $commandsOnRun = []): ContainerStarted;

    /**
     * Starts the container if it does not already exist.
     *
     * If the container doesn't exist, it will be created and started with the provided commands.
     * If the container already exists, no action will be taken.
     *
     * @param array $commandsOnRun Commands to be executed after the container is started, if it doesn't already exist.
     * @return ContainerStarted The started container.
     * @throws DockerCommandExecutionFailed If the execution of the Docker command fails.
     */
    public function runIfNotExists(array $commandsOnRun = []): ContainerStarted;

    /**
     * Copies files or directories from the host to the container.
     *
     * @param string $pathOnHost The path on the host where the files/directories are located.
     * @param string $pathOnContainer The path on the container where the files/directories will be copied.
     * @return DockerContainer The container instance with the copied files.
     */
    public function copyToContainer(string $pathOnHost, string $pathOnContainer): DockerContainer;

    /**
     * Makes the container wait for a specific condition to be met before proceeding.
     *
     * @param ContainerWait $wait The waiting mechanism or condition to be applied.
     * @return DockerContainer The container instance with the wait condition applied.
     */
    public function withWait(ContainerWait $wait): DockerContainer;

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
