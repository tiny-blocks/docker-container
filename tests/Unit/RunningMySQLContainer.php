<?php

declare(strict_types=1);

namespace Test\Unit;

use Test\Models\InspectResponseFixture;
use TinyBlocks\DockerContainer\DisposableContainer;
use TinyBlocks\DockerContainer\MySQL\MySQLContainerStarted;
use TinyBlocks\DockerContainer\MySQLDockerContainer;

final class RunningMySQLContainer
{
    private function __construct()
    {
    }

    public static function startWith(
        ClientMock $client,
        string $database,
        string $hostname,
        ?int $port = null
    ): MySQLContainerStarted&DisposableContainer {
        $container = MySQLDockerContainer::from(image: 'mysql:8.4', client: $client)
            ->withName(name: $hostname)
            ->withDatabase(database: $database)
            ->withRootPassword(rootPassword: 'root');

        $portTemplate = '%d/tcp';
        $exposedPorts = !is_null($port) ? [sprintf($portTemplate, $port) => (object)[]] : [];

        $databaseTemplate = 'MYSQL_DATABASE=%s';

        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: $hostname,
                environment: [
                    sprintf($databaseTemplate, $database),
                    'MYSQL_ROOT_PASSWORD=root'
                ],
                exposedPorts: $exposedPorts
            )
        );

        $client->withDockerExecuteResponse(output: 'mysqld is alive');
        $client->withDockerExecuteResponse(output: '');

        return $container->run();
    }
}
