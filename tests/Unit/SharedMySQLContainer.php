<?php

declare(strict_types=1);

namespace Test\Unit;

use Test\Models\InspectResponseFixture;
use TinyBlocks\DockerContainer\MySQL\MySQLContainerStarted;
use TinyBlocks\DockerContainer\MySQLDockerContainer;
use TinyBlocks\DockerContainer\SharedContainer;

final class SharedMySQLContainer
{
    private function __construct()
    {
    }

    public static function reusedWith(
        ClientMock $client,
        string $database,
        string $hostname
    ): MySQLContainerStarted&SharedContainer {
        $container = MySQLDockerContainer::from(image: 'mysql:8.4', client: $client)
            ->withName(name: $hostname)
            ->withDatabase(database: $database)
            ->withRootPassword(rootPassword: 'root');

        $databaseTemplate = 'MYSQL_DATABASE=%s';

        $client->withDockerListResponse(output: InspectResponseFixture::containerId());
        $client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: $hostname,
                environment: [
                    sprintf($databaseTemplate, $database),
                    'MYSQL_ROOT_PASSWORD=root'
                ],
                exposedPorts: ['3306/tcp' => (object)[]]
            )
        );

        $client->withDockerExecuteResponse(output: 'mysqld is alive');
        $client->withDockerExecuteResponse(output: '');

        return $container->runIfNotExists();
    }
}
