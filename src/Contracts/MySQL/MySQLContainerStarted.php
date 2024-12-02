<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Contracts\MySQL;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;

/**
 * Extends the functionality of a started container to include MySQL-specific operations.
 */
interface MySQLContainerStarted extends ContainerStarted
{
    /**
     * Default JDBC options for connecting to the MySQL container.
     */
    public const array DEFAULT_JDBC_OPTIONS = [
        'useSSL'                  => 'false',
        'useUnicode'              => 'yes',
        'characterEncoding'       => 'UTF-8',
        'allowPublicKeyRetrieval' => 'true'
    ];

    /**
     * Generates and returns a JDBC URL for connecting to the MySQL container.
     *
     * The URL is built using the container's hostname, port, and database name,
     * with optional query parameters for additional configurations.
     *
     * @param array $options An array of key-value pairs to append to the JDBC URL.
     *                       Defaults to {@see DEFAULT_JDBC_OPTIONS}.
     * @return string The generated JDBC URL.
     */
    public function getJdbcUrl(array $options = self::DEFAULT_JDBC_OPTIONS): string;
}
