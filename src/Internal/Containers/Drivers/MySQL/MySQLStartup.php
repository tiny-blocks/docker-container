<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL;

use TinyBlocks\DockerContainer\ContainerStarted;
use TinyBlocks\DockerContainer\Waits\Conditions\MySQL\MySQLReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;
use TinyBlocks\Time\Duration;

final readonly class MySQLStartup
{
    private const array EPHEMERAL_SERVER_ARGUMENTS = ['--skip-log-bin'];
    private const int DEFAULT_READINESS_TIMEOUT_IN_SECONDS = 30;

    private function __construct(
        private array $grantedHosts,
        private array $serverArguments,
        private Duration $readinessTimeout
    ) {
    }

    public static function create(): MySQLStartup
    {
        return new MySQLStartup(
            grantedHosts: [],
            serverArguments: [],
            readinessTimeout: Duration::fromSeconds(seconds: self::DEFAULT_READINESS_TIMEOUT_IN_SECONDS)
        );
    }

    public function complete(ContainerStarted $containerStarted): void
    {
        ContainerWaitForDependency::untilReady(condition: MySQLReady::from(container: $containerStarted))
            ->withTimeout(timeout: $this->readinessTimeout)
            ->waitBefore();

        $environmentVariables = $containerStarted->getEnvironmentVariables();
        $database = $environmentVariables->getValueBy(key: 'MYSQL_DATABASE');

        if ($database !== '' || !empty($this->grantedHosts)) {
            $setup = MySQLCommands::setupDatabase(
                database: $database,
                grantedHosts: $this->grantedHosts,
                rootPassword: $environmentVariables->getValueBy(key: 'MYSQL_ROOT_PASSWORD')
            );
            $containerStarted->executeAfterStarted(commands: $setup);
        }
    }

    public function commandsFor(array $commands): array
    {
        return [...$commands, ...$this->serverArguments];
    }

    public function withGrantedHosts(array $hosts): MySQLStartup
    {
        return new MySQLStartup(
            grantedHosts: $hosts,
            serverArguments: $this->serverArguments,
            readinessTimeout: $this->readinessTimeout
        );
    }

    public function withEphemeralStorage(): MySQLStartup
    {
        return new MySQLStartup(
            grantedHosts: $this->grantedHosts,
            serverArguments: self::EPHEMERAL_SERVER_ARGUMENTS,
            readinessTimeout: $this->readinessTimeout
        );
    }

    public function withReadinessTimeout(Duration $timeout): MySQLStartup
    {
        return new MySQLStartup(
            grantedHosts: $this->grantedHosts,
            serverArguments: $this->serverArguments,
            readinessTimeout: $timeout
        );
    }
}
