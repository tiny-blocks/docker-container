<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits\Conditions\MySQL;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;

final readonly class MySQLReady implements ContainerReady
{
    private function __construct(private ContainerStarted $container)
    {
    }

    public static function from(ContainerStarted $container): MySQLReady
    {
        return new MySQLReady(container: $container);
    }

    public function isReady(): bool
    {
        $rootPassword = $this->container
            ->getEnvironmentVariables()
            ->getValueBy(key: 'MYSQL_ROOT_PASSWORD');

        return $this->container
            ->executeAfterStarted(commands: ['mysqladmin', 'ping', '-h', '127.0.0.1', "-p$rootPassword"])
            ->isSuccessful();
    }
}
