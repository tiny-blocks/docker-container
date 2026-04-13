<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL;

use TinyBlocks\DockerContainer\Contracts\Address;
use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Contracts\EnvironmentVariables;
use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Contracts\MySQL\MySQLContainerStarted;

final readonly class MySQLStarted implements MySQLContainerStarted
{
    private const int DEFAULT_MYSQL_PORT = 3306;

    private function __construct(private ContainerStarted $containerStarted)
    {
    }

    public static function from(ContainerStarted $containerStarted): MySQLStarted
    {
        return new MySQLStarted(containerStarted: $containerStarted);
    }

    public function getId(): string
    {
        return $this->containerStarted->getId();
    }

    public function getName(): string
    {
        return $this->containerStarted->getName();
    }

    public function getAddress(): Address
    {
        return $this->containerStarted->getAddress();
    }

    public function remove(): void
    {
        $this->containerStarted->remove();
    }

    public function stopOnShutdown(): void
    {
        $this->containerStarted->stopOnShutdown();
    }

    public function getEnvironmentVariables(): EnvironmentVariables
    {
        return $this->containerStarted->getEnvironmentVariables();
    }

    public function stop(int $timeoutInWholeSeconds = self::DEFAULT_TIMEOUT_IN_WHOLE_SECONDS): ExecutionCompleted
    {
        return $this->containerStarted->stop(timeoutInWholeSeconds: $timeoutInWholeSeconds);
    }

    public function executeAfterStarted(array $commands): ExecutionCompleted
    {
        return $this->containerStarted->executeAfterStarted(commands: $commands);
    }

    public function getJdbcUrl(array $options = self::DEFAULT_JDBC_OPTIONS): string
    {
        $address = $this->getAddress();
        $port = $address->getPorts()->firstExposedPort() ?? self::DEFAULT_MYSQL_PORT;
        $hostname = $address->getHostname();
        $database = $this->getEnvironmentVariables()->getValueBy(key: 'MYSQL_DATABASE');

        $baseUrl = sprintf('jdbc:mysql://%s:%d/%s', $hostname, $port, $database);

        if (!empty($options)) {
            return sprintf('%s?%s', $baseUrl, http_build_query($options));
        }

        return $baseUrl;
    }
}
