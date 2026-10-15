<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL;

use TinyBlocks\DockerContainer\ContainerStarted;

final readonly class JdbcUrl
{
    private const int DEFAULT_MYSQL_PORT = 3306;

    private function __construct(private int $port, private string $database, private string $hostname)
    {
    }

    public static function from(ContainerStarted $containerStarted): JdbcUrl
    {
        $address = $containerStarted->getAddress();

        return new JdbcUrl(
            port: ($address->getPorts()->firstExposedPort() ?? self::DEFAULT_MYSQL_PORT),
            database: $containerStarted->getEnvironmentVariables()->getValueBy(key: 'MYSQL_DATABASE'),
            hostname: $address->getHostname()
        );
    }

    public function toString(array $options): string
    {
        $template = 'jdbc:mysql://%s:%d/%s';
        $baseUrl = sprintf($template, $this->hostname, $this->port, $this->database);

        if (empty($options)) {
            return $baseUrl;
        }

        $template = '%s?%s';

        return sprintf($template, $baseUrl, http_build_query($options));
    }
}
