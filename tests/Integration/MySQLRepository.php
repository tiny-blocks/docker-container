<?php

declare(strict_types=1);

namespace Test\Integration;

use PDO;
use TinyBlocks\DockerContainer\Contracts\ContainerStarted;

final readonly class MySQLRepository
{
    private function __construct(private PDO $connection)
    {
    }

    public static function connectFrom(ContainerStarted $container): MySQLRepository
    {
        $address = $container->getAddress();
        $environmentVariables = $container->getEnvironmentVariables();
        var_dump($address->getIp());
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s',
            $address->getIp(),
            $address->getPorts()->firstExposedPort(),
            $environmentVariables->getValueBy(key: 'MYSQL_DATABASE')
        );

        $connection = new PDO(
            $dsn,
            $environmentVariables->getValueBy(key: 'MYSQL_USER'),
            $environmentVariables->getValueBy(key: 'MYSQL_PASSWORD')
        );

        return new MySQLRepository(connection: $connection);
    }

    public function allRecordsFrom(string $table): array
    {
        return $this->connection
            ->query(sprintf('SELECT * FROM %s', $table))
            ->fetchAll(PDO::FETCH_ASSOC);
    }
}
