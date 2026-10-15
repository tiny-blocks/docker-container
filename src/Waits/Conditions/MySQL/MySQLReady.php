<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits\Conditions\MySQL;

use Throwable;
use TinyBlocks\DockerContainer\ContainerStarted;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;

/**
 * Readiness condition that holds once the MySQL server inside a started container answers a ping.
 */
final readonly class MySQLReady implements ContainerReady
{
    private function __construct(private ContainerStarted $container)
    {
    }

    /**
     * Creates a MySQLReady from a started MySQL container.
     *
     * @param ContainerStarted $container The started container running the MySQL server.
     * @return MySQLReady The created readiness condition.
     */
    public static function from(ContainerStarted $container): MySQLReady
    {
        return new MySQLReady(container: $container);
    }

    public function isReady(): bool
    {
        try {
            $rootPassword = $this->container
                ->getEnvironmentVariables()
                ->getValueBy(key: 'MYSQL_ROOT_PASSWORD');

            $template = 'MYSQL_PWD=%s';

            return $this->container
                ->executeAfterStarted(commands: [
                    'env',
                    sprintf($template, $rootPassword),
                    'mysqladmin',
                    'ping',
                    '-h',
                    '127.0.0.1'
                ])
                ->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }
}
