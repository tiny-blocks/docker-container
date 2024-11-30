<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Contracts\MySQL\MySQLContainerStarted;
use TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL\MySQLCommands;
use TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL\MySQLStarted;
use TinyBlocks\DockerContainer\Waits\Conditions\MySQL\MySQLReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;

class MySQLDockerContainer extends GenericDockerContainer implements MySQLContainer
{
    private array $grantedHosts = [];

    public function run(array $commandsOnRun = []): MySQLContainerStarted
    {
        $containerStarted = parent::run(commandsOnRun: $commandsOnRun);
        $environmentVariables = $containerStarted->getEnvironmentVariables();

        $database = $environmentVariables->getValueBy(key: 'MYSQL_DATABASE');
        $rootPassword = $environmentVariables->getValueBy(key: 'MYSQL_ROOT_PASSWORD');

        if (!empty($this->grantedHosts)) {
            $condition = MySQLReady::from(container: $containerStarted);
            $waitForDependency = ContainerWaitForDependency::untilReady(condition: $condition);
            $waitForDependency->wait();

            $command = MySQLCommands::createDatabase(database: $database, rootPassword: $rootPassword);
            $containerStarted->executeAfterStarted(commands: [$command]);

            foreach ($this->grantedHosts as $host) {
                $command = MySQLCommands::grantPrivilegesToRoot(host: $host, rootPassword: $rootPassword);
                $containerStarted->executeAfterStarted(commands: [$command]);
            }
        }

        return MySQLStarted::from(containerStarted: $containerStarted);
    }

    public function runIfNotExists(array $commandsOnRun = []): MySQLContainerStarted
    {
        $containerStarted = parent::runIfNotExists(commandsOnRun: $commandsOnRun);

        return MySQLStarted::from(containerStarted: $containerStarted);
    }

    public function withTimezone(string $timezone): static
    {
        $this->withEnvironmentVariable(key: 'TZ', value: $timezone);

        return $this;
    }

    public function withUsername(string $user): static
    {
        $this->withEnvironmentVariable(key: 'MYSQL_USER', value: $user);

        return $this;
    }

    public function withPassword(string $password): static
    {
        $this->withEnvironmentVariable(key: 'MYSQL_PASSWORD', value: $password);

        return $this;
    }

    public function withDatabase(string $database): static
    {
        $this->withEnvironmentVariable(key: 'MYSQL_DATABASE', value: $database);

        return $this;
    }

    public function withRootPassword(string $rootPassword): static
    {
        $this->withEnvironmentVariable(key: 'MYSQL_ROOT_PASSWORD', value: $rootPassword);

        return $this;
    }

    public function withGrantedHosts(array $hosts = ['%', '172.%']): static
    {
        $this->grantedHosts = $hosts;

        return $this;
    }
}
