<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Exceptions\ContainerExitUnsuccessful;
use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\Exceptions\ImageNameEmpty;
use TinyBlocks\DockerContainer\Internal\Client\CommandLineDockerClient;
use TinyBlocks\DockerContainer\Internal\Containers\Drivers\Flyway\FlywaySettings;
use TinyBlocks\Time\Duration;

/**
 * Immutable definition of a Flyway Docker container, whose commands each run Flyway once and block until it exits.
 *
 * <p>Each command runs in a fresh container with a unique generated name, removed once Flyway exits, so parallel
 * test runs never collide on it. Every <code>with*</code> method returns a new definition and leaves the receiver
 * untouched.</p>
 */
final readonly class FlywayDockerContainer
{
    private const string MIGRATIONS_ON_CONTAINER = '/flyway/migrations';
    private const int DEFAULT_TIMEOUT_IN_SECONDS = 120;

    private function __construct(
        private Duration $timeout,
        private FlywaySettings $settings,
        private GenericDockerContainer $container
    ) {
    }

    /**
     * Creates a FlywayDockerContainer from a Flyway Docker image.
     *
     * @param string $image The Flyway Docker image name (e.g., "flyway/flyway:12-alpine").
     * @param DockerClient $client The client that executes the Docker commands, the local docker executable by
     *                             default.
     * @return FlywayDockerContainer The created container definition.
     * @throws ImageNameEmpty If the image name is empty.
     */
    public static function from(
        string $image,
        DockerClient $client = new CommandLineDockerClient()
    ): FlywayDockerContainer {
        return new FlywayDockerContainer(
            timeout: Duration::fromSeconds(seconds: self::DEFAULT_TIMEOUT_IN_SECONDS),
            settings: FlywaySettings::create(),
            container: GenericDockerContainer::from(image: $image, client: $client)
        );
    }

    /**
     * Runs the Flyway repair command and blocks until Flyway exits.
     *
     * @return ExecutionCompleted The successful outcome, carrying the Flyway output.
     * @throws DockerCommandExecutionFailed If a Docker command fails.
     * @throws ContainerWaitTimeout If the MySQL source is not ready or Flyway does not exit within the timeout.
     * @throws ContainerExitUnsuccessful If Flyway exits with a non-zero status, carrying the Flyway error output.
     */
    public function repair(): ExecutionCompleted
    {
        return $this->settings
            ->applyTo(container: $this->container)
            ->runToCompletion(timeout: $this->timeout, commands: ['repair']);
    }

    /**
     * Runs the Flyway migrate command and blocks until Flyway exits.
     *
     * @return ExecutionCompleted The successful outcome, carrying the Flyway output.
     * @throws DockerCommandExecutionFailed If a Docker command fails.
     * @throws ContainerWaitTimeout If the MySQL source is not ready or Flyway does not exit within the timeout.
     * @throws ContainerExitUnsuccessful If Flyway exits with a non-zero status, carrying the Flyway error output.
     */
    public function migrate(): ExecutionCompleted
    {
        return $this->settings
            ->applyTo(container: $this->container)
            ->runToCompletion(timeout: $this->timeout, commands: ['migrate']);
    }

    /**
     * Runs the Flyway validate command and blocks until Flyway exits.
     *
     * @return ExecutionCompleted The successful outcome, carrying the Flyway output.
     * @throws DockerCommandExecutionFailed If a Docker command fails.
     * @throws ContainerWaitTimeout If the MySQL source is not ready or Flyway does not exit within the timeout.
     * @throws ContainerExitUnsuccessful If Flyway exits with a non-zero status, including a failed validation.
     */
    public function validate(): ExecutionCompleted
    {
        return $this->settings
            ->applyTo(container: $this->container)
            ->runToCompletion(timeout: $this->timeout, commands: ['validate']);
    }

    /**
     * Returns a copy of the FlywayDockerContainer with the history table replaced.
     *
     * @param string $table The history table name, "schema_history" by default once withSource() is called.
     * @return FlywayDockerContainer The copy with the new history table.
     */
    public function withTable(string $table): FlywayDockerContainer
    {
        return $this->withEnvironmentVariable(key: 'FLYWAY_TABLE', value: $table);
    }

    /**
     * Returns a copy of the FlywayDockerContainer with the target schema replaced.
     *
     * @param string $schema The schema name, the source database by default once withSource() is called.
     * @return FlywayDockerContainer The copy with the new target schema.
     */
    public function withSchema(string $schema): FlywayDockerContainer
    {
        return $this->withEnvironmentVariable(key: 'FLYWAY_SCHEMAS', value: $schema);
    }

    /**
     * Returns a copy of the FlywayDockerContainer that migrates the database of a MySQL container.
     *
     * <p>The MySQL container is started by the first command that runs, through
     * {@see MySQLDockerContainer::runIfNotExists()}, so it is reused when a container with its name is already
     * running. Defining the source starts nothing. Flyway then connects with the JDBC URL of the started container,
     * the root credentials (from <code>MYSQL_ROOT_PASSWORD</code>), the target schema (from
     * <code>MYSQL_DATABASE</code>) and the history table (<code>schema_history</code>). withCredentials(),
     * withSchema() and withTable() override these defaults, whether they are called before or after this method.</p>
     *
     * @param MySQLDockerContainer $container The MySQL container definition holding the database to migrate.
     * @return FlywayDockerContainer The copy bound to the MySQL container.
     */
    public function withSource(MySQLDockerContainer $container): FlywayDockerContainer
    {
        return new FlywayDockerContainer(
            timeout: $this->timeout,
            settings: $this->settings->withSource(source: $container),
            container: $this->container
        );
    }

    /**
     * Returns a copy of the FlywayDockerContainer with the Docker network replaced.
     *
     * @param string $name The name of the Docker network.
     * @return FlywayDockerContainer The copy with the new network.
     */
    public function withNetwork(string $name): FlywayDockerContainer
    {
        return new FlywayDockerContainer(
            timeout: $this->timeout,
            settings: $this->settings,
            container: $this->container->withNetwork(name: $name)
        );
    }

    /**
     * Returns a copy of the FlywayDockerContainer with the timeout of each command replaced.
     *
     * <p>The timeout bounds how long a command waits for Flyway to exit. It defaults to 120 seconds.</p>
     *
     * @param Duration $timeout The longest time a command waits for Flyway to exit.
     * @return FlywayDockerContainer The copy with the new timeout.
     */
    public function withTimeout(Duration $timeout): FlywayDockerContainer
    {
        return new FlywayDockerContainer(timeout: $timeout, settings: $this->settings, container: $this->container);
    }

    /**
     * Returns a copy of the FlywayDockerContainer that applies the migrations of a host directory.
     *
     * <p>The directory is copied into the container at <code>/flyway/migrations</code> before Flyway starts.</p>
     *
     * @param string $pathOnHost The absolute path on the host holding the migration files.
     * @return FlywayDockerContainer The copy with the migrations.
     */
    public function withMigrations(string $pathOnHost): FlywayDockerContainer
    {
        $template = 'filesystem:%s';

        return new FlywayDockerContainer(
            timeout: $this->timeout,
            settings: $this->settings->with(
                key: 'FLYWAY_LOCATIONS',
                value: sprintf($template, self::MIGRATIONS_ON_CONTAINER)
            ),
            container: $this->container->copyToContainer(
                pathOnHost: $pathOnHost,
                pathOnContainer: self::MIGRATIONS_ON_CONTAINER
            )
        );
    }

    /**
     * Runs the Flyway clean command followed by migrate, and blocks until Flyway exits.
     *
     * <p>Enables the Flyway clean command for this run, which Flyway disables by default. The migrations are
     * applied when this method returns.</p>
     *
     * @return ExecutionCompleted The successful outcome, carrying the Flyway output.
     * @throws DockerCommandExecutionFailed If a Docker command fails.
     * @throws ContainerWaitTimeout If the MySQL source is not ready or Flyway does not exit within the timeout.
     * @throws ContainerExitUnsuccessful If Flyway exits with a non-zero status, carrying the Flyway error output.
     */
    public function cleanAndMigrate(): ExecutionCompleted
    {
        return $this->settings
            ->applyTo(container: $this->container)
            ->withEnvironmentVariable(key: 'FLYWAY_CLEAN_DISABLED', value: 'false')
            ->runToCompletion(timeout: $this->timeout, commands: ['clean', 'migrate']);
    }

    /**
     * Returns a gated view of the FlywayDockerContainer whose commands run only while an environment
     * variable is enabled.
     *
     * <p>The variable is enabled when its value is <code>1</code>, and it is read each time a command is called.
     * While it is not enabled, a command returns <code>null</code> without issuing any Docker command, so neither
     * Flyway nor its MySQL source is started. This keeps the migrations of a test run in the single process that
     * sets the variable.</p>
     *
     * @param string $variable The name of the environment variable that enables the commands.
     * @return FlywayContainerGated The gated view of this definition.
     */
    public function onlyWhenEnabled(string $variable): FlywayContainerGated
    {
        return FlywayContainerGated::from(flyway: $this, variable: $variable);
    }

    /**
     * Returns a copy of the FlywayDockerContainer with the database credentials replaced.
     *
     * @param string $password The database password.
     * @param string $username The database username.
     * @return FlywayDockerContainer The copy with the new credentials.
     */
    public function withCredentials(string $password, string $username): FlywayDockerContainer
    {
        return $this
            ->withEnvironmentVariable(key: 'FLYWAY_USER', value: $username)
            ->withEnvironmentVariable(key: 'FLYWAY_PASSWORD', value: $password);
    }

    /**
     * Returns a copy of the FlywayDockerContainer with the number of database connection retries replaced.
     *
     * @param int $retries The number of connection retries, one second apart.
     * @return FlywayDockerContainer The copy with the new number of retries.
     */
    public function withConnectRetries(int $retries): FlywayDockerContainer
    {
        return $this->withEnvironmentVariable(key: 'FLYWAY_CONNECT_RETRIES', value: (string)$retries);
    }

    /**
     * Returns a copy of the FlywayDockerContainer with an environment variable added.
     *
     * <p>Any Flyway setting can be configured through its <code>FLYWAY_*</code> environment variable.</p>
     *
     * @param string $key The environment variable name.
     * @param string $value The environment variable value.
     * @return FlywayDockerContainer The copy with the added environment variable.
     */
    public function withEnvironmentVariable(string $key, string $value): FlywayDockerContainer
    {
        return new FlywayDockerContainer(
            timeout: $this->timeout,
            settings: $this->settings->with(key: $key, value: $value),
            container: $this->container
        );
    }

    /**
     * Returns a copy of the FlywayDockerContainer that rejects migrations whose file names break the Flyway
     * naming convention, which Flyway otherwise skips silently.
     *
     * @return FlywayDockerContainer The copy with migration naming validation enabled.
     */
    public function withMigrationNamingValidation(): FlywayDockerContainer
    {
        return $this->withEnvironmentVariable(key: 'FLYWAY_VALIDATE_MIGRATION_NAMING', value: 'true');
    }
}
