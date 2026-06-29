<?php

declare(strict_types=1);

namespace Test\Unit;

use Test\Models\InspectResponseFixture;
use TinyBlocks\DockerContainer\MySQL\MySQLContainerStarted;

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
    ): MySQLContainerStarted {
        $container = TestableMySQLDockerContainer::createWith(
            name: $hostname,
            image: 'mysql:8.4',
            client: $client
        )
            ->withDatabase(database: $database)
            ->withRootPassword(rootPassword: 'root');

        $portTemplate = '%d/tcp';
        $exposedPorts = !is_null($port) ? [sprintf($portTemplate, $port) => (object)[]] : [];

        $databaseTemplate = 'MYSQL_DATABASE=%s';

        $client->withDockerRunResponse(output: InspectResponseFixture::containerId());
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
