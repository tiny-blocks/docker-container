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
            commandHandler: $containerStarted->commandHandler
        );
    }

    public function getJdbcUrl(array $options = self::DEFAULT_JDBC_OPTIONS): string
    {
        $address = $this->getAddress();
        $port = $address->getPorts()->firstExposedPort() ?? self::DEFAULT_MYSQL_PORT;
        $hostname = $address->getHostname();
        $database = $this->getEnvironmentVariables()->getValueBy(key: 'MYSQL_DATABASE');

        $baseUrl = sprintf('jdbc:mysql://%s:%d/%s', $hostname, $port, $database);

        if (!empty($options)) {
            $queryString = http_build_query($options);
            return sprintf('%s?%s', $baseUrl, $queryString);
        }

        return $baseUrl;
    }
}
