<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Exceptions\ContainerExitUnsuccessful;
use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\Exceptions\ImageNameEmpty;
use TinyBlocks\DockerContainer\Internal\Client\CommandLineDockerClient;
use TinyBlocks\DockerContainer\Internal\Containers\ContainerLauncher;
use TinyBlocks\DockerContainer\Internal\Containers\ContainerLookup;
use TinyBlocks\DockerContainer\Internal\Containers\ContainerSharing;
use TinyBlocks\DockerContainer\Internal\Containers\ContainerWaits;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\DockerContainer\Internal\Containers\OneShotContainer;
use TinyBlocks\DockerContainer\Waits\ContainerWaitAfterStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitBeforeStarted;
use TinyBlocks\Time\Duration;

/**
 * Immutable definition of a Docker container built from any image, which runs it on demand.
 *
 * <p>Every <code>with*</code> method returns a new definition and leaves the receiver untouched.</p>
 */
final readonly class GenericDockerContainer
{
    private function __construct(
        private ContainerWaits $waits,
        private DockerClient $client,
        private ContainerDefinition $definition
    ) {
    }

    /**
     * Creates a GenericDockerContainer from a Docker image.
     *
     * <p>The container gets a unique generated name until withName() replaces it.</p>
     *
     * @param string $image The Docker image name (e.g., "mysql:8.4").
     * @param DockerClient $client The client that executes the Docker commands, the local docker executable by
     *                             default.
     * @return GenericDockerContainer The created container definition.
     * @throws ImageNameEmpty If the image name is empty.
     */
    public static function from(
        string $image,
        DockerClient $client = new CommandLineDockerClient()
    ): GenericDockerContainer {
        return new GenericDockerContainer(
            waits: ContainerWaits::none(),
            client: $client,
            definition: ContainerDefinition::create(image: $image)
        );
    }

    /**
     * Creates and starts the container, then applies the wait strategies registered with withWaitAfterStarted().
     *
     * <p>Files registered with copyToContainer() are copied after the container is created and before it
     * starts.</p>
     *
     * @param array<int, string> $commands The command, and its arguments, the container runs on startup.
     * @return DisposableContainer The started container, owned by this process.
     * @throws DockerCommandExecutionFailed If a Docker command fails.
     * @throws ContainerWaitTimeout If a wait strategy is not satisfied within its timeout.
     */
    public function run(array $commands = []): DisposableContainer
    {
        $this->waits->waitBeforeRun();

        $launcher = new ContainerLauncher(client: $this->client);
        $id = $launcher->start(commands: $commands, definition: $this->definition);
        $containerStarted = new ContainerLookup(client: $this->client)->byId(id: $id, name: $this->definition->name);

        $this->waits->waitAfterStarted(containerStarted: $containerStarted);

        return $containerStarted;
    }

    /**
     * Returns a copy of the GenericDockerContainer with the container name replaced.
     *
     * <p>The name identifies the container that runIfNotExists() shares among processes.</p>
     *
     * @param string $name The container name.
     * @return GenericDockerContainer The copy with the new name.
     */
    public function withName(string $name): GenericDockerContainer
    {
        return new GenericDockerContainer(
            waits: $this->waits,
            client: $this->client,
            definition: $this->definition->withName(name: $name)
        );
    }

    /**
     * Returns a copy of the GenericDockerContainer with the Docker network replaced.
     *
     * <p>The network is created when the container runs, if it does not exist yet, and labeled
     * <code>tiny-blocks.docker-container=true</code>.</p>
     *
     * @param string $name The name of the Docker network.
     * @return GenericDockerContainer The copy with the new network.
     */
    public function withNetwork(string $name): GenericDockerContainer
    {
        return new GenericDockerContainer(
            waits: $this->waits,
            client: $this->client,
            definition: $this->definition->withNetwork(name: $name)
        );
    }

    /**
     * Starts the container unless one with the same name already exists, in which case that one is reused.
     *
     * <p>The returned container is shared by every PHP process that asks for the same name, as in mutation
     * testing, so no single process removes it. When running inside Docker, a reaper container removes it once the
     * current test runner container exits. The wait strategies registered with withWaitAfterStarted() apply only to
     * a newly started container.</p>
     *
     * @param array<int, string> $commands The command, and its arguments, a new container runs on startup.
     * @return SharedContainer The existing or newly started container.
     * @throws DockerCommandExecutionFailed If a Docker command fails.
     * @throws ContainerWaitTimeout If a wait strategy is not satisfied within its timeout.
     */
    public function runIfNotExists(array $commands = []): SharedContainer
    {
        return new ContainerSharing(client: $this->client)->share(
            name: $this->definition->name,
            start: fn(): DisposableContainer => $this->run(commands: $commands)
        );
    }

    /**
     * Returns a copy of the GenericDockerContainer with a tmpfs mount added inside the container.
     *
     * <p>The mounted directory lives in memory, starts empty, and its content is discarded when the container
     * stops.</p>
     *
     * @param string $pathOnContainer The directory inside the container backed by memory.
     * @return GenericDockerContainer The copy with the added tmpfs mount.
     */
    public function withTmpfsMount(string $pathOnContainer): GenericDockerContainer
    {
        return new GenericDockerContainer(
            waits: $this->waits,
            client: $this->client,
            definition: $this->definition->withTmpfsMount(pathOnContainer: $pathOnContainer)
        );
    }

    /**
     * Returns a copy of the GenericDockerContainer with a file or directory added to copy into the container.
     *
     * <p>The copy happens after the container is created and before it starts, so its command already sees
     * the files.</p>
     *
     * @param string $pathOnHost The absolute path on the host.
     * @param string $pathOnContainer The target path inside the container.
     * @return GenericDockerContainer The copy with the added file or directory.
     */
    public function copyToContainer(string $pathOnHost, string $pathOnContainer): GenericDockerContainer
    {
        return new GenericDockerContainer(
            waits: $this->waits,
            client: $this->client,
            definition: $this->definition->withCopyInstruction(
                pathOnHost: $pathOnHost,
                pathOnContainer: $pathOnContainer
            )
        );
    }

    /**
     * Runs the container until it exits and returns what it wrote.
     *
     * <p>The container is created, receives the files registered with copyToContainer(), then starts attached.
     * It is removed once it exits, successfully or not, or once the timeout elapses.</p>
     *
     * @param Duration $timeout The longest time the container may run.
     * @param array<int, string> $commands The command, and its arguments, the container runs.
     * @return ExecutionCompleted The successful outcome, carrying the standard output of the container.
     * @throws DockerCommandExecutionFailed If the container cannot be created or receive its files.
     * @throws ContainerWaitTimeout If a wait strategy before the run is not satisfied, or if the container does
     *                              not exit within the timeout.
     * @throws ContainerExitUnsuccessful If the container exits with a non-zero status, carrying its error output.
     */
    public function runToCompletion(Duration $timeout, array $commands = []): ExecutionCompleted
    {
        $this->waits->waitBeforeRun();

        $oneShot = new OneShotContainer(client: $this->client);

        return $oneShot->run(timeout: $timeout, commands: $commands, definition: $this->definition);
    }

    /**
     * Returns a copy of the GenericDockerContainer with a port mapping added between the host and the container.
     *
     * @param int $portOnHost The port on the host machine, or 0 for a port Docker picks.
     * @param int $portOnContainer The port inside the container.
     * @return GenericDockerContainer The copy with the added port mapping.
     */
    public function withPortMapping(int $portOnHost, int $portOnContainer): GenericDockerContainer
    {
        return new GenericDockerContainer(
            waits: $this->waits,
            client: $this->client,
            definition: $this->definition->withPortMapping(portOnHost: $portOnHost, portOnContainer: $portOnContainer)
        );
    }

    /**
     * Returns a copy of the GenericDockerContainer with a volume mapping added between the host and the container.
     *
     * @param string $pathOnHost The absolute path on the host.
     * @param string $pathOnContainer The target path inside the container.
     * @return GenericDockerContainer The copy with the added volume mapping.
     */
    public function withVolumeMapping(string $pathOnHost, string $pathOnContainer): GenericDockerContainer
    {
        return new GenericDockerContainer(
            waits: $this->waits,
            client: $this->client,
            definition: $this->definition->withVolumeMapping(
                pathOnHost: $pathOnHost,
                pathOnContainer: $pathOnContainer
            )
        );
    }

    /**
     * Returns a copy of the GenericDockerContainer with a wait strategy added, applied before the container runs.
     *
     * <p>The wait strategies apply in the order they were added.</p>
     *
     * @param ContainerWaitBeforeStarted $wait The wait strategy applied before the container runs.
     * @return GenericDockerContainer The copy with the added wait strategy.
     */
    public function withWaitBeforeRun(ContainerWaitBeforeStarted $wait): GenericDockerContainer
    {
        return new GenericDockerContainer(
            waits: $this->waits->withBeforeRun(wait: $wait),
            client: $this->client,
            definition: $this->definition
        );
    }

    /**
     * Returns a copy of the GenericDockerContainer that Docker keeps after it stops.
     *
     * <p>By default, Docker removes the container once it stops.</p>
     *
     * @return GenericDockerContainer The copy without automatic removal.
     */
    public function withoutAutoRemove(): GenericDockerContainer
    {
        return new GenericDockerContainer(
            waits: $this->waits,
            client: $this->client,
            definition: $this->definition->withoutAutoRemove()
        );
    }

    /**
     * Returns a copy of the GenericDockerContainer with a wait strategy added, applied after run() starts the
     * container.
     *
     * <p>The wait strategies apply in the order they were added.</p>
     *
     * @param ContainerWaitAfterStarted $wait The wait strategy applied after the container starts.
     * @return GenericDockerContainer The copy with the added wait strategy.
     */
    public function withWaitAfterStarted(ContainerWaitAfterStarted $wait): GenericDockerContainer
    {
        return new GenericDockerContainer(
            waits: $this->waits->withAfterStarted(wait: $wait),
            client: $this->client,
            definition: $this->definition
        );
    }

    /**
     * Returns a copy of the GenericDockerContainer with an environment variable added.
     *
     * @param string $key The environment variable name.
     * @param string $value The environment variable value.
     * @return GenericDockerContainer The copy with the added environment variable.
     */
    public function withEnvironmentVariable(string $key, string $value): GenericDockerContainer
    {
        return new GenericDockerContainer(
            waits: $this->waits,
            client: $this->client,
            definition: $this->definition->withEnvironmentVariable(key: $key, value: $value)
        );
    }
}
