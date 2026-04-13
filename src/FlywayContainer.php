<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Contracts\MySQL\MySQLContainerStarted;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

/**
 * Defines the contract for building and running a Flyway Docker container.
 */
interface FlywayContainer
{
    /**
     * Creates a new Flyway container instance from the given Docker image.
     *
     * @param string $image The Flyway Docker image name (e.g., "flyway/flyway:12-alpine").
     * @param string|null $name An optional name for the container.
     * @return static A new Flyway container instance.
     */
    public static function from(string $image, ?string $name = null): static;

    /**
     * Runs the Flyway migrate command.
     *
     * @return ContainerStarted The started container instance.
     * @throws DockerCommandExecutionFailed If the command fails.
     */
    public function migrate(): ContainerStarted;

    /**
     * Runs the Flyway repair command.
     *
     * @return ContainerStarted The started container instance.
     * @throws DockerCommandExecutionFailed If the command fails.
     */
    public function repair(): ContainerStarted;

    /**
     * Runs the Flyway validate command.
     *
     * @return ContainerStarted The started container instance.
     * @throws DockerCommandExecutionFailed If the command fails.
     */
    public function validate(): ContainerStarted;

    /**
     * Starts pulling the Flyway image in the background.
     *
     * @return static The current container instance for method chaining.
     */
    public function pullImage(): static;

    /**
     * Overrides the Flyway history table name. Defaults to "schema_history" when withSource() is called.
     *
     * @param string $table The table name.
     * @return static The current container instance for method chaining.
     */
    public function withTable(string $table): static;

    /**
     * Overrides the target database schema. Defaults to the database name from the source
     * container when withSource() is called.
     *
     * @param string $schema The schema name.
     * @return static The current container instance for method chaining.
     */
    public function withSchema(string $schema): static;

    /**
     * Connects the container to a specific Docker network.
     *
     * @param string $name The name of the Docker network.
     * @return static The current container instance for method chaining.
     */
    public function withNetwork(string $name): static;

    /**
     * Sets the database source from a started MySQL container. Automatically configures
     * the JDBC URL, credentials, target schema (from MYSQL_DATABASE), and history table
     * (defaults to "schema_history"). Use withSchema() or withTable() after this method
     * to override the defaults.
     *
     * @param MySQLContainerStarted $container The running MySQL container.
     * @param string $username The database username.
     * @param string $password The database password.
     * @return static The current container instance for method chaining.
     */
    public function withSource(MySQLContainerStarted $container, string $username, string $password): static;

    /**
     * Configures whether Flyway's clean command is disabled.
     *
     * @param bool $disabled True to disable clean, false to allow it.
     * @return static The current container instance for method chaining.
     */
    public function withCleanDisabled(bool $disabled): static;

    /**
     * Sets the migration files from a host directory.
     *
     * @param string $pathOnHost The absolute path on the host containing migration files.
     * @return static The current container instance for method chaining.
     */
    public function withMigrations(string $pathOnHost): static;

    /**
     * Sets the number of retries when connecting to the database.
     *
     * @param int $retries The number of connection retries.
     * @return static The current container instance for method chaining.
     */
    public function withConnectRetries(int $retries): static;

    /**
     * Runs the Flyway clean command followed by migrate.
     *
     * @return ContainerStarted The started container instance.
     * @throws DockerCommandExecutionFailed If the command fails.
     */
    public function cleanAndMigrate(): ContainerStarted;

    /**
     * Configures whether Flyway should validate migration naming conventions.
     *
     * @param bool $enabled True to enable validation, false to disable it.
     * @return static The current container instance for method chaining.
     */
    public function withValidateMigrationNaming(bool $enabled): static;
}
