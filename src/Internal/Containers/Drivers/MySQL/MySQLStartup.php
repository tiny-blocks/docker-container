<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL;

use TinyBlocks\DockerContainer\ContainerStarted;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\Waits\Conditions\MySQL\MySQLReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;
use TinyBlocks\Time\Duration;

final readonly class MySQLStartup
{
    private const array EPHEMERAL_SERVER_ARGUMENTS = ['--skip-log-bin'];
    private const int DEFAULT_READINESS_TIMEOUT_IN_SECONDS = 60;

    private function __construct(
        private Duration $readinessTimeout,
        private array $grantedHosts = [],
        private array $serverArguments = []
    ) {
    }

    public static function create(): MySQLStartup
    {
        return new MySQLStartup(
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

        if ($database === '' && empty($this->grantedHosts)) {
            return;
        }

        $setup = MySQLCommands::setupDatabase(
            database: $database,
            grantedHosts: $this->grantedHosts,
            rootPassword: $environmentVariables->getValueBy(key: 'MYSQL_ROOT_PASSWORD')
        );
        $execution = $containerStarted->executeAfterStarted(commands: $setup);

        if (!$execution->isSuccessful()) {
            throw DockerCommandExecutionFailed::from(reason: $execution->getOutput(), command: implode(' ', $setup));
        }
    }

    public function commandsFor(array $commands): array
    {
        return [...$commands, ...$this->serverArguments];
    }

    public function withGrantedHosts(array $hosts): MySQLStartup
    {
        return clone($this, ['grantedHosts' => $hosts]);
    }

    public function withEphemeralStorage(): MySQLStartup
    {
        return clone($this, ['serverArguments' => self::EPHEMERAL_SERVER_ARGUMENTS]);
    }

    public function withReadinessTimeout(Duration $timeout): MySQLStartup
    {
        return clone($this, ['readinessTimeout' => $timeout]);
    }
}
