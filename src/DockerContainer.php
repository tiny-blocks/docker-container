<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\Waits\ContainerWaitAfterStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitBeforeStarted;

/**
 * Defines the contract for building and running a Docker container.
 */
interface DockerContainer
{
    /**
     * Creates a new container instance from the given Docker image.
     *
     * @param string $image The Docker image name (e.g., "mysql:8.1").
     * @param string|null $name An optional name for the container.
     * @return static A new container instance.
     */
    public static function from(string $image, ?string $name = null): static;

    /**
     * Runs the container, optionally executing commands after startup.
     *
     * @param array<int, string> $commands Commands to execute on container startup.
     * @param ContainerWaitAfterStarted|null $waitAfterStarted Optional wait strategy applied after
     *                                                         the container starts.
     * @return ContainerStarted The started container instance.
     * @throws DockerCommandExecutionFailed If the run command fails.
     */
    public function run(array $commands = [], ?ContainerWaitAfterStarted $waitAfterStarted = null): ContainerStarted;

    /**
     * Runs the container only if a container with the same name does not already exist.
     *
     * @param array<int, string> $commands Commands to execute on container startup.
     * @param ContainerWaitAfterStarted|null $waitAfterStarted Optional wait strategy applied after
     *                                                         the container starts.
     * @return ContainerStarted The started container instance (existing or newly created).
     * @throws DockerCommandExecutionFailed If the run command fails.
     */
    public function runIfNotExists(
        array $commands = [],
        ?ContainerWaitAfterStarted $waitAfterStarted = null
    ): ContainerStarted;

    /**
     * Starts pulling the container image in the background. When run() or runIfNotExists()
     * is called, it waits for the pull to complete before starting the container.
     * Calling this method on multiple containers before running them enables parallel image pulls.
     *
     * @return static The current container instance for method chaining.
     */
    public function pullImage(): static;

    /**
     * Registers a file or directory to be copied into the container after it starts.
     *
     * @param string $pathOnHost The absolute path on the host.
     * @param string $pathOnContainer The target path inside the container.
     * @return static The current container instance for method chaining.
     */
    public function copyToContainer(string $pathOnHost, string $pathOnContainer): static;

    /**
     * Sets the Docker network the container should join. The network is created
     * automatically when the container is started via run() or runIfNotExists(),
     * if it does not already exist.
     *
     * @param string $name The name of the Docker network.
     * @return static The current container instance for method chaining.
     */
    public function withNetwork(string $name): static;

    /**
     * Adds a port mapping between the host and the container.
     *
     * @param int $portOnHost The port on the host machine.
     * @param int $portOnContainer The port inside the container.
     * @return static The current container instance for method chaining.
     */
    public function withPortMapping(int $portOnHost, int $portOnContainer): static;

    /**
     * Sets a wait strategy to be applied before the container runs.
     *
     * @param ContainerWaitBeforeStarted $wait The wait strategy to apply before starting.
     * @return static The current container instance for method chaining.
     */
    public function withWaitBeforeRun(ContainerWaitBeforeStarted $wait): static;

    /**
     * Disables automatic removal of the container when it stops.
     *
     * @return static The current container instance for method chaining.
     */
    public function withoutAutoRemove(): static;

    /**
     * Adds a volume mapping between the host and the container.
     *
     * @param string $pathOnHost The absolute path on the host.
     * @param string $pathOnContainer The target path inside the container.
     * @return static The current container instance for method chaining.
     */
    public function withVolumeMapping(string $pathOnHost, string $pathOnContainer): static;

    /**
     * Adds an environment variable to the container.
     *
     * @param string $key The environment variable name.
     * @param string $value The environment variable value.
     * @return static The current container instance for method chaining.
     */
    public function withEnvironmentVariable(string $key, string $value): static;
}
