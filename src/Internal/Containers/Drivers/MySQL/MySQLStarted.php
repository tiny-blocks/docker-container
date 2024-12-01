<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Contracts\MySQL\MySQLContainerStarted;
use TinyBlocks\DockerContainer\Internal\Containers\Started;

final readonly class MySQLStarted extends Started implements MySQLContainerStarted
{
    private const int DEFAULT_MYSQL_PORT = 3306;

    public static function from(ContainerStarted $containerStarted): MySQLStarted
    {
        return new MySQLStarted(
            container: $containerStarted->container,
            containerHandler: $containerStarted->containerHandler
        );
    }

    public function getJdbcUrl(?string $options = null): string
    {
        $address = $this->getAddress();
        $port = $address->getPorts()->firstExposedPort() ?? self::DEFAULT_MYSQL_PORT;
        $hostname = $address->getHostname();
        $database = $this->getEnvironmentVariables()->getValueBy(key: 'MYSQL_DATABASE');

        $baseUrl = sprintf('jdbc:mysql://%s:%d/%s', $hostname, $port, $database);

        return $options ? sprintf('%s?%s', $baseUrl, ltrim($options, '?')) : $baseUrl;
    }
}
