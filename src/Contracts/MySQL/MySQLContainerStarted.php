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
     * Generates and returns a JDBC URL for connecting to the MySQL container.
     *
     * The URL is built using the container's hostname, port, and database name,
     * with optional query parameters for additional configurations.
     *
     * @param string|null $options A query string to append to the JDBC URL.
     *                             Example: "useSSL=false&serverTimezone=UTC".
     * @return string The generated JDBC URL.
     */
    public function getJdbcUrl(?string $options = null): string;
}
