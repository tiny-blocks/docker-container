<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Contracts\MySQL\MySQLContainerStarted;
use TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL\MySQLCommands;
use TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL\MySQLStarted;
use TinyBlocks\DockerContainer\Waits\Conditions\MySQL\MySQLReady;
use TinyBlocks\DockerContainer\Waits\ContainerWait;
use TinyBlocks\DockerContainer\Waits\ContainerWaitAfterStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitBeforeStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;

class MySQLDockerContainer implements MySQLContainer
{
    /** @var array<int, string> */
    private array $grantedHosts = [];

    private int $readinessTimeoutInSeconds;

    protected function __construct(private GenericDockerContainer $container)
    {
        $this->readinessTimeoutInSeconds = ContainerWait::DEFAULT_TIMEOUT_IN_SECONDS;
    }

    public static function from(string $image, ?string $name = null): static
    {
        return new static(container: GenericDockerContainer::from(image: $image, name: $name));
    }

    public function pullImage(): static
    {
        $this->container->pullImage();

        return $this;
    }

    public function copyToContainer(string $pathOnHost, string $pathOnContainer): static
    {
        $this->container->copyToContainer(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer);

        return $this;
    }

    public function withNetwork(string $name): static
    {
        $this->container->withNetwork(name: $name);

        return $this;
    }

    public function withPortMapping(int $portOnHost, int $portOnContainer): static
    {
        $this->container->withPortMapping(portOnHost: $portOnHost, portOnContainer: $portOnContainer);

        return $this;
    }

    public function withWaitBeforeRun(ContainerWaitBeforeStarted $wait): static
    {
        $this->container->withWaitBeforeRun(wait: $wait);

        return $this;
    }

    public function withoutAutoRemove(): static
    {
        $this->container->withoutAutoRemove();

        return $this;
    }

    public function withVolumeMapping(string $pathOnHost, string $pathOnContainer): static
    {
        $this->container->withVolumeMapping(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer);

        return $this;
    }

    public function withEnvironmentVariable(string $key, string $value): static
    {
        $this->container->withEnvironmentVariable(key: $key, value: $value);

        return $this;
    }

    public function withTimezone(string $timezone): static
    {
        $this->container->withEnvironmentVariable(key: 'TZ', value: $timezone);

        return $this;
    }

    public function withUsername(string $user): static
    {
        $this->container->withEnvironmentVariable(key: 'MYSQL_USER', value: $user);

        return $this;
    }

    public function withPassword(string $password): static
    {
        $this->container->withEnvironmentVariable(key: 'MYSQL_PASSWORD', value: $password);

        return $this;
    }

    public function withDatabase(string $database): static
    {
        $this->container->withEnvironmentVariable(key: 'MYSQL_DATABASE', value: $database);

        return $this;
    }

    public function withRootPassword(string $rootPassword): static
    {
        $this->container->withEnvironmentVariable(key: 'MYSQL_ROOT_PASSWORD', value: $rootPassword);

        return $this;
    }

    public function withGrantedHosts(array $hosts = ['%', '172.%']): static
    {
        $this->grantedHosts = $hosts;

        return $this;
    }

    public function withReadinessTimeout(int $timeoutInSeconds): static
    {
        $this->readinessTimeoutInSeconds = $timeoutInSeconds;

        return $this;
    }

    public function runIfNotExists(
        array $commands = [],
        ?ContainerWaitAfterStarted $waitAfterStarted = null
    ): MySQLContainerStarted {
        $containerStarted = $this->container->runIfNotExists(commands: $commands);

        return MySQLStarted::from(containerStarted: $containerStarted);
    }

    public function run(
        array $commands = [],
        ?ContainerWaitAfterStarted $waitAfterStarted = null
    ): MySQLContainerStarted {
        $containerStarted = $this->container->run(commands: $commands);

        $condition = MySQLReady::from(container: $containerStarted);
        ContainerWaitForDependency::untilReady(
            condition: $condition,
            timeoutInSeconds: $this->readinessTimeoutInSeconds
        )->waitBefore();

        $environmentVariables = $containerStarted->getEnvironmentVariables();
        $database = $environmentVariables->getValueBy(key: 'MYSQL_DATABASE');
        $rootPassword = $environmentVariables->getValueBy(key: 'MYSQL_ROOT_PASSWORD');

        if (!empty($database) || !empty($this->grantedHosts)) {
            $containerStarted->executeAfterStarted(
                commands: [
                    MySQLCommands::setupDatabase(
                        database: $database,
                        rootPassword: $rootPassword,
                        grantedHosts: $this->grantedHosts
                    )
                ]
            );
        }

        return MySQLStarted::from(containerStarted: $containerStarted);
    }
}
