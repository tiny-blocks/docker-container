<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

/**
 * Defines operations for creating and managing MySQL Docker containers.
 */
interface MySQLContainer extends DockerContainer
{
    /**
     * Sets the timezone for the MySQL container.
     *
     * @param string $timezone The desired timezone (e.g., 'America/Sao_Paulo').
     * @return static The instance of the MySQL container with the timezone environment variable set.
     */
    public function withTimezone(string $timezone): static;

    /**
     * Sets the MySQL username.
     *
     * @param string $user The MySQL username to configure.
     * @return static The instance of the MySQL container with the username set.
     */
    public function withUsername(string $user): static;

    /**
     * Sets the MySQL user password.
     *
     * @param string $password The password for the MySQL user.
     * @return static The instance of the MySQL container with the password set.
     */
    public function withPassword(string $password): static;

    /**
     * Sets the database to be created in the MySQL container.
     *
     * @param string $database The name of the database to create.
     * @return static The instance of the MySQL container with the database set.
     */
    public function withDatabase(string $database): static;

    /**
     * Sets the root password for MySQL.
     *
     * @param string $rootPassword The root password for MySQL.
     * @return static The instance of the MySQL container with the root password set.
     */
    public function withRootPassword(string $rootPassword): static;

    /**
     * Sets the hosts that the MySQL root user will have privileges for.
     * The default is `['%', '172.%']`.
     *
     * @param array $hosts List of hosts to grant privileges to the root user.
     * @return static The instance of the MySQL container with the granted hosts set.
     */
    public function withGrantedHosts(array $hosts = ['%', '172.%']): static;
}
