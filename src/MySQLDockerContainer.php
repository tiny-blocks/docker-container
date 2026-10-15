<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\Exceptions\ImageNameEmpty;
use TinyBlocks\DockerContainer\Internal\Client\CommandLineDockerClient;
use TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL\MySQLShared;
use TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL\MySQLStarted;
use TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL\MySQLStartup;
use TinyBlocks\DockerContainer\MySQL\MySQLContainerStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitBeforeStarted;
use TinyBlocks\Time\Duration;

/**
 * Immutable definition of a MySQL Docker container, which runs it and hands it over ready for connections.
 *
 * <p>Every <code>with*</code> method returns a new definition and leaves the receiver untouched.</p>
 */
final readonly class MySQLDockerContainer
{
    private const string DATA_DIRECTORY = '/var/lib/mysql';

    private function __construct(private MySQLStartup $startup, private GenericDockerContainer $container)
    {
    }

    /**
     * Creates a MySQLDockerContainer from a MySQL Docker image.
     *
     * <p>The container gets a unique generated name until withName() replaces it.</p>
     *
     * @param string $image The MySQL Docker image name (e.g., "mysql:8.4").
     * @param DockerClient $client The client that executes the Docker commands, the local docker executable by
     *                             default.
     * @return MySQLDockerContainer The created container definition.
     * @throws ImageNameEmpty If the image name is empty.
     */
    public static function from(
        string $image,
        DockerClient $client = new CommandLineDockerClient()
    ): MySQLDockerContainer {
        return new MySQLDockerContainer(
            startup: MySQLStartup::create(),
            container: GenericDockerContainer::from(image: $image, client: $client)
        );
    }

    /**
     * Starts the container and waits until MySQL accepts connections.
     *
     * <p>Once MySQL answers, the database is created and root is granted access from the granted hosts, when
     * either was configured.</p>
     *
     * @param array<int, string> $commands The arguments passed to the MySQL server on startup.
     * @return MySQLContainerStarted&DisposableContainer The started container, owned by this process and ready for
     *                                                   connections.
     * @throws DockerCommandExecutionFailed If a Docker command fails.
     * @throws ContainerWaitTimeout If MySQL does not accept connections within the readiness timeout.
     */
    public function run(array $commands = []): MySQLContainerStarted&DisposableContainer
    {
        $containerStarted = $this->container->run(commands: $this->startup->commandsFor(commands: $commands));
        $this->startup->complete(containerStarted: $containerStarted);

        return MySQLStarted::from(containerStarted: $containerStarted);
    }

    /**
     * Returns a copy of the MySQLDockerContainer with the container name replaced.
     *
     * <p>The name identifies the container that runIfNotExists() shares among processes.</p>
     *
     * @param string $name The container name.
     * @return MySQLDockerContainer The copy with the new name.
     */
    public function withName(string $name): MySQLDockerContainer
    {
        return new MySQLDockerContainer(startup: $this->startup, container: $this->container->withName(name: $name));
    }

    /**
     * Returns a copy of the MySQLDockerContainer with the Docker network replaced.
     *
     * @param string $name The name of the Docker network.
     * @return MySQLDockerContainer The copy with the new network.
     */
    public function withNetwork(string $name): MySQLDockerContainer
    {
        return new MySQLDockerContainer(startup: $this->startup, container: $this->container->withNetwork(name: $name));
    }

    /**
     * Returns a copy of the MySQLDockerContainer with the database created on startup replaced.
     *
     * @param string $database The database name.
     * @return MySQLDockerContainer The copy with the new database.
     */
    public function withDatabase(string $database): MySQLDockerContainer
    {
        return $this->withEnvironmentVariable(key: 'MYSQL_DATABASE', value: $database);
    }

    /**
     * Returns a copy of the MySQLDockerContainer with the password of the user created on startup replaced.
     *
     * @param string $password The user password.
     * @return MySQLDockerContainer The copy with the new password.
     */
    public function withPassword(string $password): MySQLDockerContainer
    {
        return $this->withEnvironmentVariable(key: 'MYSQL_PASSWORD', value: $password);
    }

    /**
     * Returns a copy of the MySQLDockerContainer with the timezone replaced.
     *
     * @param string $timezone The timezone identifier (e.g., "America/Sao_Paulo").
     * @return MySQLDockerContainer The copy with the new timezone.
     */
    public function withTimezone(string $timezone): MySQLDockerContainer
    {
        return $this->withEnvironmentVariable(key: 'TZ', value: $timezone);
    }

    /**
     * Returns a copy of the MySQLDockerContainer with the user created on startup replaced.
     *
     * @param string $user The username.
     * @return MySQLDockerContainer The copy with the new user.
     */
    public function withUsername(string $user): MySQLDockerContainer
    {
        return $this->withEnvironmentVariable(key: 'MYSQL_USER', value: $user);
    }

    /**
     * Starts the container unless one with the same name already exists, then waits until MySQL accepts
     * connections.
     *
     * <p>The container is shared as described in {@see GenericDockerContainer::runIfNotExists()}. The readiness
     * wait and the database setup run for a reused container too, so a container another process has just
     * started is handed over only once it is ready. The setup statements are idempotent.</p>
     *
     * @param array<int, string> $commands The arguments passed to a new MySQL server on startup.
     * @return MySQLContainerStarted&SharedContainer The existing or newly started container, ready for connections.
     * @throws DockerCommandExecutionFailed If a Docker command fails.
     * @throws ContainerWaitTimeout If MySQL does not accept connections within the readiness timeout.
     */
    public function runIfNotExists(array $commands = []): MySQLContainerStarted&SharedContainer
    {
        $containerStarted = $this->container->runIfNotExists(
            commands: $this->startup->commandsFor(commands: $commands)
        );
        $this->startup->complete(containerStarted: $containerStarted);

        return MySQLShared::from(containerStarted: $containerStarted);
    }

    /**
     * Returns a copy of the MySQLDockerContainer with a file or directory added to copy into the container.
     *
     * @param string $pathOnHost The absolute path on the host.
     * @param string $pathOnContainer The target path inside the container.
     * @return MySQLDockerContainer The copy with the added file or directory.
     */
    public function copyToContainer(string $pathOnHost, string $pathOnContainer): MySQLDockerContainer
    {
        return new MySQLDockerContainer(
            startup: $this->startup,
            container: $this->container->copyToContainer(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer)
        );
    }

    /**
     * Returns a copy of the MySQLDockerContainer with a port mapping added between the host and the container.
     *
     * @param int $portOnHost The port on the host machine, or 0 for a port Docker picks.
     * @param int $portOnContainer The port inside the container.
     * @return MySQLDockerContainer The copy with the added port mapping.
     */
    public function withPortMapping(int $portOnHost, int $portOnContainer): MySQLDockerContainer
    {
        return new MySQLDockerContainer(
            startup: $this->startup,
            container: $this->container->withPortMapping(portOnHost: $portOnHost, portOnContainer: $portOnContainer)
        );
    }

    /**
     * Returns a copy of the MySQLDockerContainer with the hosts root is granted access from replaced.
     *
     * @param array<int, string> $hosts The host patterns (e.g., ["%", "172.%"]).
     * @return MySQLDockerContainer The copy with the new granted hosts.
     */
    public function withGrantedHosts(array $hosts = ['%', '172.%']): MySQLDockerContainer
    {
        return new MySQLDockerContainer(
            startup: $this->startup->withGrantedHosts(hosts: $hosts),
            container: $this->container
        );
    }

    /**
     * Returns a copy of the MySQLDockerContainer with the root password replaced.
     *
     * @param string $rootPassword The root password.
     * @return MySQLDockerContainer The copy with the new root password.
     */
    public function withRootPassword(string $rootPassword): MySQLDockerContainer
    {
        return $this->withEnvironmentVariable(key: 'MYSQL_ROOT_PASSWORD', value: $rootPassword);
    }

    /**
     * Returns a copy of the MySQLDockerContainer with a volume mapping added between the host and the container.
     *
     * @param string $pathOnHost The absolute path on the host.
     * @param string $pathOnContainer The target path inside the container.
     * @return MySQLDockerContainer The copy with the added volume mapping.
     */
    public function withVolumeMapping(string $pathOnHost, string $pathOnContainer): MySQLDockerContainer
    {
        return new MySQLDockerContainer(
            startup: $this->startup,
            container: $this->container->withVolumeMapping(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer)
        );
    }

    /**
     * Returns a copy of the MySQLDockerContainer with a wait strategy added, applied before the container runs.
     *
     * @param ContainerWaitBeforeStarted $wait The wait strategy applied before the container runs.
     * @return MySQLDockerContainer The copy with the added wait strategy.
     */
    public function withWaitBeforeRun(ContainerWaitBeforeStarted $wait): MySQLDockerContainer
    {
        return new MySQLDockerContainer(
            startup: $this->startup,
            container: $this->container->withWaitBeforeRun(wait: $wait)
        );
    }

    /**
     * Returns a copy of the MySQLDockerContainer that Docker keeps after it stops.
     *
     * @return MySQLDockerContainer The copy without automatic removal.
     */
    public function withoutAutoRemove(): MySQLDockerContainer
    {
        return new MySQLDockerContainer(startup: $this->startup, container: $this->container->withoutAutoRemove());
    }

    /**
     * Returns a copy of the MySQLDockerContainer that keeps its data in memory.
     *
     * <p>The data directory is a tmpfs mount, so the data is discarded when the container stops, and the server runs
     * without the binary log, which would otherwise grow in that memory. Meant for test databases, never for data
     * that must outlive the container.</p>
     *
     * @return MySQLDockerContainer The copy with ephemeral storage.
     */
    public function withEphemeralStorage(): MySQLDockerContainer
    {
        return new MySQLDockerContainer(
            startup: $this->startup->withEphemeralStorage(),
            container: $this->container->withTmpfsMount(pathOnContainer: self::DATA_DIRECTORY)
        );
    }

    /**
     * Returns a copy of the MySQLDockerContainer with the readiness timeout replaced.
     *
     * <p>The readiness timeout bounds how long run() and runIfNotExists() wait for MySQL to accept connections.
     * It defaults to 30 seconds.</p>
     *
     * @param Duration $timeout The longest time to wait for MySQL to accept connections.
     * @return MySQLDockerContainer The copy with the new readiness timeout.
     */
    public function withReadinessTimeout(Duration $timeout): MySQLDockerContainer
    {
        return new MySQLDockerContainer(
            startup: $this->startup->withReadinessTimeout(timeout: $timeout),
            container: $this->container
        );
    }

    /**
     * Returns a copy of the MySQLDockerContainer with an environment variable added.
     *
     * @param string $key The environment variable name.
     * @param string $value The environment variable value.
     * @return MySQLDockerContainer The copy with the added environment variable.
     */
    public function withEnvironmentVariable(string $key, string $value): MySQLDockerContainer
    {
        return new MySQLDockerContainer(
            startup: $this->startup,
            container: $this->container->withEnvironmentVariable(key: $key, value: $value)
        );
    }
}
