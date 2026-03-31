<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

/**
 * Defines the contract for building and running a MySQL Docker container.
 */
interface MySQLContainer extends DockerContainer
{
    /**
     * Sets the timezone for the MySQL container.
     *
     * @param string $timezone The timezone identifier (e.g., "America/Sao_Paulo").
     * @return static The current container instance for method chaining.
     */
    public function withTimezone(string $timezone): static;

    /**
     * Sets the MySQL user to be created on startup.
     *
     * @param string $user The username.
     * @return static The current container instance for method chaining.
     */
    public function withUsername(string $user): static;

    /**
     * Sets the password for the MySQL user created on startup.
     *
     * @param string $password The user password.
     * @return static The current container instance for method chaining.
     */
    public function withPassword(string $password): static;

    /**
     * Sets the default database to be created on startup.
     *
     * @param string $database The database name.
     * @return static The current container instance for method chaining.
     */
    public function withDatabase(string $database): static;

    /**
     * Sets the root password for the MySQL instance.
     *
     * @param string $rootPassword The root password.
     * @return static The current container instance for method chaining.
     */
    public function withRootPassword(string $rootPassword): static;

    /**
     * Sets the hosts to which the root user is granted privileges.
     *
     * @param array<int, string> $hosts The list of host patterns (e.g., ["%", "172.%"]).
     * @return static The current container instance for method chaining.
     */
    public function withGrantedHosts(array $hosts = ['%', '172.%']): static;

    /**
     * Sets the maximum time in seconds to wait for MySQL to be ready.
     *
     * @param int $timeoutInSeconds The timeout in seconds.
     * @return static The instance with the readiness timeout set.
     */
    public function withReadinessTimeout(int $timeoutInSeconds): static;
}
