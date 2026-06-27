<?php

declare(strict_types=1);

namespace Test\Unit;

use PHPUnit\Framework\TestCase;
use Test\Models\InspectResponseFixture;

final class FlywayDockerContainerTest extends TestCase
{
    private ClientMock $client;

    protected function setUp(): void
    {
        $this->client = new ClientMock();
    }

    public function testPullImageStartsBackgroundPull(): void
    {
        /** @Given a Flyway container with image pulling enabled */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-pull',
            image: 'flyway/flyway:12-alpine',
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
        self::assertSame('flyway-pull', $started->getName());

        /** @And the docker pull command should have been executed */
        self::assertStringContainsString(
            'docker pull flyway/flyway:12-alpine',
            implode(PHP_EOL, $this->client->getExecutedCommandLines())
        );
    }

    public function testRepairRunsFlywayRepairCommand(): void
    {
        /** @Given a Flyway container */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-beta',
            image: 'flyway/flyway:12-alpine',
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
        self::assertSame('flyway-beta', $started->getName());
        self::assertStringContainsString('repair', implode(PHP_EOL, $this->client->getExecutedCommandLines()));
    }

    public function testWithTableOverridesDefaultTable(): void
    {
        /** @Given a running MySQL container */
        $mySQLStarted = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'test_db',
            hostname: 'custom-table-db'
        );

        /** @And a Flyway container with source and a table override */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-override-table',
            image: 'flyway/flyway:12-alpine',
            client: $this->client
        )
            ->withSource(password: 'root', username: 'root', container: $mySQLStarted)
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
        self::assertStringContainsString(
            'FLYWAY_TABLE=flyway_history',
            implode(PHP_EOL, $this->client->getExecutedCommandLines())
        );
    }

    public function testCleanAndMigrateRunsBothCommands(): void
    {
        /** @Given a Flyway container */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-clean-migrate',
            image: 'flyway/flyway:12-alpine',
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
        self::assertSame('flyway-clean-migrate', $started->getName());
        self::assertStringContainsString('clean migrate', implode(PHP_EOL, $this->client->getExecutedCommandLines()));

        /** @And the wait time should be exactly 10 seconds */
        self::assertGreaterThanOrEqual(9.5, $elapsed);
        self::assertLessThanOrEqual(10.5, $elapsed);
    }

    public function testMigrateRunsFlywayMigrateCommand(): void
    {
        /** @Given a Flyway container */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-alpha',
            image: 'flyway/flyway:12-alpine',
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
        self::assertSame('flyway-alpha', $started->getName());
        self::assertStringContainsString('migrate', implode(PHP_EOL, $this->client->getExecutedCommandLines()));
    }

    public function testValidateRunsFlywayValidateCommand(): void
    {
        /** @Given a Flyway container */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-gamma',
            image: 'flyway/flyway:12-alpine',
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
        self::assertSame('flyway-gamma', $started->getName());
        self::assertStringContainsString('validate', implode(PHP_EOL, $this->client->getExecutedCommandLines()));
    }

    public function testWithNetworkConfiguresDockerNetwork(): void
    {
        /** @Given a Flyway container with a network */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-network',
            image: 'flyway/flyway:12-alpine',
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
        self::assertStringContainsString('--network=test-network', implode(PHP_EOL, $commandLines));
        self::assertStringContainsString(
            'docker network create --label tiny-blocks.docker-container=true test-network',
            implode(PHP_EOL, $commandLines)
        );
    }

    public function testWithSchemaOverridesAutoDetectedSchema(): void
    {
        /** @Given a running MySQL container with database "original" */
        $mySQLStarted = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'original',
            hostname: 'override-db'
        );

        /** @And a Flyway container with source and a schema override */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-override-schema',
            image: 'flyway/flyway:12-alpine',
            client: $this->client
        )
            ->withSource(password: 'root', username: 'root', container: $mySQLStarted)
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
        self::assertStringContainsString(
            'FLYWAY_SCHEMAS=custom_schema',
            implode(PHP_EOL, $this->client->getExecutedCommandLines())
        );
    }

    public function testWithMigrationsConfiguresCopyAndLocation(): void
    {
        /** @Given a Flyway container with migrations configured */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-migrations',
            image: 'flyway/flyway:12-alpine',
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
        self::assertStringContainsString(
            'FLYWAY_LOCATIONS=filesystem:/flyway/migrations',
            implode(PHP_EOL, $commandLines)
        );
        self::assertStringContainsString('docker cp /host/migrations', implode(PHP_EOL, $commandLines));
    }

    public function testWithSourceSetsDefaultSchemaHistoryTable(): void
    {
        /** @Given a running MySQL container */
        $mySQLStarted = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'test_db',
            hostname: 'table-db'
        );

        /** @And a Flyway container configured with the MySQL source */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-table',
            image: 'flyway/flyway:12-alpine',
            client: $this->client
        )->withSource(password: 'root', username: 'root', container: $mySQLStarted);

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
        self::assertStringContainsString(
            'FLYWAY_TABLE=schema_history',
            implode(PHP_EOL, $this->client->getExecutedCommandLines())
        );
    }

    public function testWithCleanDisabledSetsEnvironmentVariable(): void
    {
        /** @Given a Flyway container with clean disabled */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-clean-disabled',
            image: 'flyway/flyway:12-alpine',
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
        self::assertStringContainsString(
            'FLYWAY_CLEAN_DISABLED=true',
            implode(PHP_EOL, $this->client->getExecutedCommandLines())
        );
    }

    public function testWithConnectRetriesSetsEnvironmentVariable(): void
    {
        /** @Given a Flyway container with connect retries configured */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-retries',
            image: 'flyway/flyway:12-alpine',
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
        self::assertStringContainsString(
            'FLYWAY_CONNECT_RETRIES=10',
            implode(PHP_EOL, $this->client->getExecutedCommandLines())
        );
    }

    public function testWithSourceConfiguresJdbcUrlAndCredentials(): void
    {
        /** @Given a running MySQL container */
        $mySQLStarted = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'app_database',
            hostname: 'source-db'
        );

        /** @And a Flyway container configured with the MySQL source */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-source',
            image: 'flyway/flyway:12-alpine',
            client: $this->client
        )->withSource(password: 'secret', username: 'admin', container: $mySQLStarted);

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
        self::assertStringContainsString(
            'FLYWAY_URL=jdbc:mysql://source-db:3306/app_database',
            implode(PHP_EOL, $commandLines)
        );
        self::assertStringContainsString('FLYWAY_USER=admin', implode(PHP_EOL, $commandLines));
        self::assertStringContainsString('FLYWAY_PASSWORD=secret', implode(PHP_EOL, $commandLines));

        /** @And a MySQL readiness check should have been executed before Flyway started */
        $mysqladminPingCount = count(
            array_filter(
                $commandLines,
                static fn(string $cmd): bool => str_contains($cmd, 'mysqladmin ping')
            )
        );
        self::assertSame(2, $mysqladminPingCount);
    }

    public function testWithSourceAutoDetectsSchemaFromMySQLContainer(): void
    {
        /** @Given a running MySQL container with database "products" */
        $mySQLStarted = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'products',
            hostname: 'schema-db'
        );

        /** @And a Flyway container configured with the MySQL source */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-schema',
            image: 'flyway/flyway:12-alpine',
            client: $this->client
        )->withSource(password: 'root', username: 'root', container: $mySQLStarted);

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
        self::assertStringContainsString(
            'FLYWAY_SCHEMAS=products',
            implode(PHP_EOL, $this->client->getExecutedCommandLines())
        );
    }

    public function testWithValidateMigrationNamingSetsEnvironmentVariable(): void
    {
        /** @Given a Flyway container with migration naming validation enabled */
        $container = TestableFlywayDockerContainer::createWith(
            name: 'flyway-naming',
            image: 'flyway/flyway:12-alpine',
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
        self::assertStringContainsString(
            'FLYWAY_VALIDATE_MIGRATION_NAMING=true',
            implode(PHP_EOL, $this->client->getExecutedCommandLines())
        );
    }
}
