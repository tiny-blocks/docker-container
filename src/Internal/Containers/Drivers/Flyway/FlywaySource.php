<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\Flyway;

use TinyBlocks\DockerContainer\MySQLDockerContainer;

final readonly class FlywaySource
{
    private const string HISTORY_TABLE = 'schema_history';
    private const string ROOT_USERNAME = 'root';

    private function __construct(private ?MySQLDockerContainer $container)
    {
    }

    public static function from(MySQLDockerContainer $container): FlywaySource
    {
        return new FlywaySource(container: $container);
    }

    public static function none(): FlywaySource
    {
        return new FlywaySource(container: null);
    }

    public function toSettings(): array
    {
        if (is_null($this->container)) {
            return [];
        }

        $sourceStarted = $this->container->runIfNotExists();
        $environmentVariables = $sourceStarted->getEnvironmentVariables();

        return [
            'FLYWAY_URL'      => $sourceStarted->getJdbcUrl(),
            'FLYWAY_USER'     => self::ROOT_USERNAME,
            'FLYWAY_TABLE'    => self::HISTORY_TABLE,
            'FLYWAY_SCHEMAS'  => $environmentVariables->getValueBy(key: 'MYSQL_DATABASE'),
            'FLYWAY_PASSWORD' => $environmentVariables->getValueBy(key: 'MYSQL_ROOT_PASSWORD')
        ];
    }
}
