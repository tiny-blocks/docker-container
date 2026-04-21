<?php

declare(strict_types=1);

namespace Test\Unit;

use PHPUnit\Framework\TestCase;
use Test\Unit\Mocks\ClientMock;
use Test\Unit\Mocks\InspectResponseFixture;
use Test\Unit\Mocks\TestableFlywayDockerContainer;
use Test\Unit\Mocks\TestableMySQLDockerContainer;
use TinyBlocks\DockerContainer\Contracts\MySQL\MySQLContainerStarted;

final class FlywayDockerContainerTest extends TestCase
{
    private ClientMock $client;

    protected function setUp(): void
    {
        $this->client = new ClientMock();
    }

    public function testMigrateRunsFlywayMigrateCommand(): void
    {
        /** @Given a Flyway container */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-alpha',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'flyway-alpha')
        );

        /** @When migrate is called */
        $started = $container->migrate();

        /** @Then the container should have executed the migrate command */
        self::assertSame(expected: 'flyway-alpha', actual: $started->getName());
        self::assertCommandLineContains(needle: 'migrate', commandLines: $this->client->getExecutedCommandLines());
    }

    public function testRepairRunsFlywayRepairCommand(): void
    {
        /** @Given a Flyway container */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-beta',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'flyway-beta')
        );

        /** @When repair is called */
        $started = $container->repair();

        /** @Then the container should have executed the repair command */
        self::assertSame(expected: 'flyway-beta', actual: $started->getName());
        self::assertCommandLineContains(needle: 'repair', commandLines: $this->client->getExecutedCommandLines());
    }

    public function testValidateRunsFlywayValidateCommand(): void
    {
        /** @Given a Flyway container */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-gamma',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'flyway-gamma')
        );

        /** @When validate is called */
        $started = $container->validate();

        /** @Then the container should have executed the validate command */
        self::assertSame(expected: 'flyway-gamma', actual: $started->getName());
        self::assertCommandLineContains(needle: 'validate', commandLines: $this->client->getExecutedCommandLines());
    }

    public function testCleanAndMigrateRunsBothCommands(): void
    {
        /** @Given a Flyway container */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-clean-migrate',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'flyway-clean-migrate')
        );

        /** @When cleanAndMigrate is called */
        $start = microtime(true);
        $started = $container->cleanAndMigrate();
        $elapsed = microtime(true) - $start;

        /** @Then the container should have executed clean followed by migrate */
        self::assertSame(expected: 'flyway-clean-migrate', actual: $started->getName());
        self::assertCommandLineContains(
            needle: 'clean migrate',
            commandLines: $this->client->getExecutedCommandLines()
        );

        /** @And the wait time should be exactly 10 seconds */
        self::assertGreaterThanOrEqual(minimum: 9.5, actual: $elapsed);
        self::assertLessThanOrEqual(maximum: 10.5, actual: $elapsed);
    }

    public function testWithSourceAutoDetectsSchemaFromMySQLContainer(): void
    {
        /** @Given a running MySQL container with database "products" */
        $mySQLStarted = $this->createRunningMySQLContainer(
            hostname: 'schema-db',
            database: 'products'
        );

        /** @And a Flyway container configured with the MySQL source */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-schema',
            client: $this->client
        )->withSource(container: $mySQLStarted, username: 'root', password: 'root');

        /** @And the MySQL readiness check succeeds during Flyway startup */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @And the Docker daemon returns valid Flyway responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'flyway-schema')
        );

        /** @When migrate is called */
        $container->migrate();

        /** @Then FLYWAY_SCHEMAS should be auto-detected from the MySQL database name */
        self::assertCommandLineContains(
            needle: 'FLYWAY_SCHEMAS=products',
            commandLines: $this->client->getExecutedCommandLines()
        );
    }

    public function testWithSourceSetsDefaultSchemaHistoryTable(): void
    {
        /** @Given a running MySQL container */
        $mySQLStarted = $this->createRunningMySQLContainer(
            hostname: 'table-db',
            database: 'test_db'
        );

        /** @And a Flyway container configured with the MySQL source */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-table',
            client: $this->client
        )->withSource(container: $mySQLStarted, username: 'root', password: 'root');

        /** @And the MySQL readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @And the Docker daemon returns valid Flyway responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'flyway-table')
        );

        /** @When migrate is called */
        $container->migrate();

        /** @Then FLYWAY_TABLE should default to "schema_history" */
        self::assertCommandLineContains(
            needle: 'FLYWAY_TABLE=schema_history',
            commandLines: $this->client->getExecutedCommandLines()
        );
    }

    public function testWithSourceConfiguresJdbcUrlAndCredentials(): void
    {
        /** @Given a running MySQL container */
        $mySQLStarted = $this->createRunningMySQLContainer(
            hostname: 'source-db',
            database: 'app_database'
        );

        /** @And a Flyway container configured with the MySQL source */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-source',
            client: $this->client
        )->withSource(container: $mySQLStarted, username: 'admin', password: 'secret');

        /** @And the MySQL readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @And the Docker daemon returns valid Flyway responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'flyway-source')
        );

        /** @When migrate is called */
        $container->migrate();

        /** @Then the docker run command should include the JDBC URL and credentials */
        $commandLines = $this->client->getExecutedCommandLines();
        self::assertCommandLineContains(
            needle: 'FLYWAY_URL=jdbc:mysql://source-db:3306/app_database',
            commandLines: $commandLines
        );
        self::assertCommandLineContains(needle: 'FLYWAY_USER=admin', commandLines: $commandLines);
        self::assertCommandLineContains(needle: 'FLYWAY_PASSWORD=secret', commandLines: $commandLines);

        /** @And a MySQL readiness check should have been executed before Flyway started */
        $mysqladminPingCount = count(
            array_filter(
                $commandLines,
                static fn(string $cmd): bool => str_contains($cmd, 'mysqladmin ping')
            )
        );
        self::assertSame(expected: 2, actual: $mysqladminPingCount);
    }

    public function testWithSchemaOverridesAutoDetectedSchema(): void
    {
        /** @Given a running MySQL container with database "original" */
        $mySQLStarted = $this->createRunningMySQLContainer(
            hostname: 'override-db',
            database: 'original'
        );

        /** @And a Flyway container with source and a schema override */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-override-schema',
            client: $this->client
        )
            ->withSource(container: $mySQLStarted, username: 'root', password: 'root')
            ->withSchema(schema: 'custom_schema');

        /** @And the MySQL readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @And the Docker daemon returns valid Flyway responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'flyway-override-schema')
        );

        /** @When migrate is called */
        $container->migrate();

        /** @Then the overridden schema should be present in the command */
        self::assertCommandLineContains(
            needle: 'FLYWAY_SCHEMAS=custom_schema',
            commandLines: $this->client->getExecutedCommandLines()
        );
    }

    public function testWithTableOverridesDefaultTable(): void
    {
        /** @Given a running MySQL container */
        $mySQLStarted = $this->createRunningMySQLContainer(
            hostname: 'custom-table-db',
            database: 'test_db'
        );

        /** @And a Flyway container with source and a table override */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-override-table',
            client: $this->client
        )
            ->withSource(container: $mySQLStarted, username: 'root', password: 'root')
            ->withTable(table: 'flyway_history');

        /** @And the MySQL readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @And the Docker daemon returns valid Flyway responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'flyway-override-table')
        );

        /** @When migrate is called */
        $container->migrate();

        /** @Then the overridden table should be present in the command */
        self::assertCommandLineContains(
            needle: 'FLYWAY_TABLE=flyway_history',
            commandLines: $this->client->getExecutedCommandLines()
        );
    }

    public function testWithMigrationsConfiguresCopyAndLocation(): void
    {
        /** @Given a Flyway container with migrations configured */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-migrations',
            client: $this->client
        )->withMigrations(pathOnHost: '/host/migrations');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'flyway-migrations')
        );

        /** @When migrate is called */
        $container->migrate();

        /** @Then the FLYWAY_LOCATIONS should point to the container migrations path */
        $commandLines = $this->client->getExecutedCommandLines();
        self::assertCommandLineContains(
            needle: 'FLYWAY_LOCATIONS=filesystem:/flyway/migrations',
            commandLines: $commandLines
        );
        self::assertCommandLineContains(needle: 'docker cp /host/migrations', commandLines: $commandLines);
    }

    public function testWithCleanDisabledSetsEnvironmentVariable(): void
    {
        /** @Given a Flyway container with clean disabled */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-clean-disabled',
            client: $this->client
        )->withCleanDisabled(disabled: true);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'flyway-clean-disabled')
        );

        /** @When migrate is called */
        $container->migrate();

        /** @Then FLYWAY_CLEAN_DISABLED should be set to true */
        self::assertCommandLineContains(
            needle: 'FLYWAY_CLEAN_DISABLED=true',
            commandLines: $this->client->getExecutedCommandLines()
        );
    }

    public function testWithConnectRetriesSetsEnvironmentVariable(): void
    {
        /** @Given a Flyway container with connect retries configured */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-retries',
            client: $this->client
        )->withConnectRetries(retries: 10);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'flyway-retries')
        );

        /** @When migrate is called */
        $container->migrate();

        /** @Then FLYWAY_CONNECT_RETRIES should be set to 10 */
        self::assertCommandLineContains(
            needle: 'FLYWAY_CONNECT_RETRIES=10',
            commandLines: $this->client->getExecutedCommandLines()
        );
    }

    public function testWithValidateMigrationNamingSetsEnvironmentVariable(): void
    {
        /** @Given a Flyway container with migration naming validation enabled */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-naming',
            client: $this->client
        )->withValidateMigrationNaming(enabled: true);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'flyway-naming')
        );

        /** @When migrate is called */
        $container->migrate();

        /** @Then FLYWAY_VALIDATE_MIGRATION_NAMING should be set to true */
        self::assertCommandLineContains(
            needle: 'FLYWAY_VALIDATE_MIGRATION_NAMING=true',
            commandLines: $this->client->getExecutedCommandLines()
        );
    }

    public function testWithNetworkConfiguresDockerNetwork(): void
    {
        /** @Given a Flyway container with a network */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-network',
            client: $this->client
        )->withNetwork(name: 'test-network');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'flyway-network',
                networkName: 'test-network'
            )
        );

        /** @When migrate is called */
        $container->migrate();

        /** @Then the docker run command should include the network and auto-creation */
        $commandLines = $this->client->getExecutedCommandLines();
        self::assertCommandLineContains(needle: '--network=test-network', commandLines: $commandLines);
        self::assertCommandLineContains(
            needle: 'docker network create --label tiny-blocks.docker-container=true test-network',
            commandLines: $commandLines
        );
    }

    public function testPullImageStartsBackgroundPull(): void
    {
        /** @Given a Flyway container with image pulling enabled */
        $container = TestableFlywayDockerContainer::createWith(
            image: 'flyway/flyway:12-alpine',
            name: 'flyway-pull',
            client: $this->client
        )->pullImage();

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'flyway-pull')
        );

        /** @When migrate is called */
        $started = $container->migrate();

        /** @Then the container should start successfully after the pull completes */
        self::assertSame(expected: 'flyway-pull', actual: $started->getName());

        /** @And the docker pull command should have been executed */
        self::assertCommandLineContains(
            needle: 'docker pull flyway/flyway:12-alpine',
            commandLines: $this->client->getExecutedCommandLines()
        );
    }

    protected function createRunningMySQLContainer(string $hostname, string $database): MySQLContainerStarted
    {
        $container = TestableMySQLDockerContainer::createWith(
            image: 'mysql:8.4',
            name: $hostname,
            client: $this->client
        )
            ->withDatabase(database: $database)
            ->withRootPassword(rootPassword: 'root');

        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: $hostname,
                environment: [
                    sprintf('MYSQL_DATABASE=%s', $database),
                    'MYSQL_ROOT_PASSWORD=root'
                ],
                exposedPorts: ['3306/tcp' => (object)[]]
            )
        );

        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');
        $this->client->withDockerExecuteResponse(output: '');

        return $container->run();
    }

    protected static function assertCommandLineContains(string $needle, array $commandLines): void
    {
        foreach ($commandLines as $commandLine) {
            if (str_contains((string)$commandLine, $needle)) {
                self::assertTrue(true);
                return;
            }
        }

        self::fail(
            sprintf(
                'Expected command containing "%s" not found in executed commands:%s%s',
                $needle,
                PHP_EOL,
                implode(PHP_EOL, $commandLines)
            )
        );
    }
}
