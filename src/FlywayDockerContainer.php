<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Contracts\MySQL\MySQLContainerStarted;
use TinyBlocks\DockerContainer\Waits\Conditions\MySQL\MySQLReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForTime;

class FlywayDockerContainer implements FlywayContainer
{
    protected function __construct(private GenericDockerContainer $container)
    {
    }

    public static function from(string $image, ?string $name = null): static
    {
        return new static(container: GenericDockerContainer::from(image: $image, name: $name));
    }

    public function migrate(): ContainerStarted
    {
        return $this->container->run(commands: ['migrate']);
    }

    public function repair(): ContainerStarted
    {
        return $this->container->run(commands: ['repair']);
    }

    public function validate(): ContainerStarted
    {
        return $this->container->run(commands: ['validate']);
    }

    public function pullImage(): static
    {
        $this->container->pullImage();

        return $this;
    }

    public function withTable(string $table): static
    {
        $this->container->withEnvironmentVariable(key: 'FLYWAY_TABLE', value: $table);

        return $this;
    }

    public function withSchema(string $schema): static
    {
        $this->container->withEnvironmentVariable(key: 'FLYWAY_SCHEMAS', value: $schema);

        return $this;
    }

    public function withNetwork(string $name): static
    {
        $this->container->withNetwork(name: $name);

        return $this;
    }

    public function withCleanDisabled(bool $disabled): static
    {
        $this->container->withEnvironmentVariable(key: 'FLYWAY_CLEAN_DISABLED', value: $disabled ? 'true' : 'false');

        return $this;
    }

    public function withConnectRetries(int $retries): static
    {
        $this->container->withEnvironmentVariable(key: 'FLYWAY_CONNECT_RETRIES', value: (string)$retries);

        return $this;
    }

    public function cleanAndMigrate(): ContainerStarted
    {
        return $this->container->run(
            commands: ['clean', 'migrate'],
            waitAfterStarted: ContainerWaitForTime::forSeconds(seconds: 10)
        );
    }

    public function withMigrations(string $pathOnHost): static
    {
        $this->container->copyToContainer(pathOnHost: $pathOnHost, pathOnContainer: '/flyway/migrations');
        $this->container->withEnvironmentVariable(key: 'FLYWAY_LOCATIONS', value: 'filesystem:/flyway/migrations');

        return $this;
    }

    public function withValidateMigrationNaming(bool $enabled): static
    {
        $this->container->withEnvironmentVariable(
            key: 'FLYWAY_VALIDATE_MIGRATION_NAMING',
            value: $enabled ? 'true' : 'false'
        );

        return $this;
    }

    public function withSource(MySQLContainerStarted $container, string $username, string $password): static
    {
        $schema = $container->getEnvironmentVariables()->getValueBy(key: 'MYSQL_DATABASE');

        $this->container->withEnvironmentVariable(key: 'FLYWAY_URL', value: $container->getJdbcUrl());
        $this->container->withEnvironmentVariable(key: 'FLYWAY_USER', value: $username);
        $this->container->withEnvironmentVariable(key: 'FLYWAY_TABLE', value: 'schema_history');
        $this->container->withEnvironmentVariable(key: 'FLYWAY_SCHEMAS', value: $schema);
        $this->container->withEnvironmentVariable(key: 'FLYWAY_PASSWORD', value: $password);
        $this->container->withWaitBeforeRun(
            wait: ContainerWaitForDependency::untilReady(condition: MySQLReady::from(container: $container))
        );

        return $this;
    }
}
