<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Contracts\MySQL;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;

/**
 * Represents a MySQL Docker container that has been started and is running.
 */
interface MySQLContainerStarted extends ContainerStarted
{
    /**
     * Default JDBC connection options for MySQL.
     *
     * @var array<string, string>
     */
    public const array DEFAULT_JDBC_OPTIONS = [
        'useSSL'                  => 'false',
        'useUnicode'              => 'yes',
        'characterEncoding'       => 'UTF-8',
        'allowPublicKeyRetrieval' => 'true'
    ];

    /**
     * Returns the JDBC connection URL for the MySQL container.
     *
     * @param array<string, string> $options JDBC connection options appended as query parameters.
     * @return string The fully constructed JDBC URL.
     */
    public function getJdbcUrl(array $options = self::DEFAULT_JDBC_OPTIONS): string;
}
