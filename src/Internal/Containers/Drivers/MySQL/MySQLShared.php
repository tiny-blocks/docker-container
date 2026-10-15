<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL;

use TinyBlocks\DockerContainer\Address;
use TinyBlocks\DockerContainer\EnvironmentVariables;
use TinyBlocks\DockerContainer\ExecutionCompleted;
use TinyBlocks\DockerContainer\MySQL\MySQLContainerStarted;
use TinyBlocks\DockerContainer\SharedContainer;
use TinyBlocks\Time\Duration;

final readonly class MySQLShared implements MySQLContainerStarted, SharedContainer
{
    private function __construct(private SharedContainer $containerStarted)
    {
    }

    public static function from(SharedContainer $containerStarted): MySQLShared
    {
        return new MySQLShared(containerStarted: $containerStarted);
    }

    public function stop(Duration $gracePeriod): ExecutionCompleted
    {
        return $this->containerStarted->stop(gracePeriod: $gracePeriod);
    }

    public function getId(): string
    {
        return $this->containerStarted->getId();
    }

    public function getName(): string
    {
        return $this->containerStarted->getName();
    }

    public function wasReused(): bool
    {
        return $this->containerStarted->wasReused();
    }

    public function getAddress(): Address
    {
        return $this->containerStarted->getAddress();
    }

    public function getJdbcUrl(array $options = self::DEFAULT_JDBC_OPTIONS): string
    {
        return JdbcUrl::from(containerStarted: $this->containerStarted)->toString(options: $options);
    }

    public function executeAfterStarted(array $commands): ExecutionCompleted
    {
        return $this->containerStarted->executeAfterStarted(commands: $commands);
    }

    public function getEnvironmentVariables(): EnvironmentVariables
    {
        return $this->containerStarted->getEnvironmentVariables();
    }
}
