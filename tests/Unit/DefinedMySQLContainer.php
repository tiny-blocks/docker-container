<?php

declare(strict_types=1);

namespace Test\Unit;

use Test\Models\InspectResponseFixture;
use TinyBlocks\DockerContainer\MySQLDockerContainer;

final class DefinedMySQLContainer
{
    private function __construct()
    {
    }

    public static function definedWith(ClientMock $client, string $database, string $hostname): MySQLDockerContainer
    {
        $databaseTemplate = 'MYSQL_DATABASE=%s';

        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: $hostname,
                environment: [
                    sprintf($databaseTemplate, $database),
                    'MYSQL_ROOT_PASSWORD=root'
                ]
            )
        );

        $client->withDockerExecuteResponse(output: 'mysqld is alive');
        $client->withDockerExecuteResponse(output: '');

        return MySQLDockerContainer::from(image: 'mysql:8.4', client: $client)
            ->withName(name: $hostname)
            ->withDatabase(database: $database)
            ->withRootPassword(rootPassword: 'root');
    }
}
