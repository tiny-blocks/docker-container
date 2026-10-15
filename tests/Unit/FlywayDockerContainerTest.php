<?php

declare(strict_types=1);

namespace Test\Unit;

use PHPUnit\Framework\TestCase;
use Test\Models\InspectResponseFixture;
use TinyBlocks\DockerContainer\Exceptions\ContainerExitUnsuccessful;
use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\FlywayDockerContainer;
use TinyBlocks\Time\Duration;

final class FlywayDockerContainerTest extends TestCase
{
    private ClientMock $client;

    protected function setUp(): void
    {
        $this->client = new ClientMock();
    }

    public function testMigrateThenFlywayCleanIsNotEnabled(): void
    {
        /** @Given a Flyway container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then Flyway should keep its own default, which disables clean */
        self::assertStringNotContainsString('FLYWAY_CLEAN_DISABLED', $this->client->getExecutedCommandLines()[0]);
    }

    public function testWithNetworkThenFlywayJoinsTheNetwork(): void
    {
        /** @Given a Flyway container with a network */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->withNetwork(name: 'test-network');

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then the network should be created and joined */
        $commandLines = implode(PHP_EOL, $this->client->getExecutedCommandLines());
        self::assertStringContainsString('--network=test-network', $commandLines);
        self::assertStringContainsString(
            'docker network create --label tiny-blocks.docker-container=true test-network',
            $commandLines
        );
    }

    public function testWithTableThenOverridesTheHistoryTable(): void
    {
        /** @Given a running MySQL container */
        $mySQLStarted = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'test_db',
            hostname: 'custom-table-db'
        );

        /** @And a Flyway container with the MySQL source and a table override */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->withSource(password: 'root', username: 'root', container: $mySQLStarted)
            ->withTable(table: 'flyway_history');

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then the overridden table should come after the default one, so it prevails */
        self::assertMatchesRegularExpression(
            '/FLYWAY_TABLE=schema_history .*FLYWAY_TABLE=flyway_history/',
            implode(PHP_EOL, $this->client->getExecutedCommandLines())
        );
    }

    public function testRepairThenRunsFlywayRepairToCompletion(): void
    {
        /** @Given a Flyway container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When repair is called */
        $flyway->repair();

        /** @Then Flyway should have been created with the repair command, run attached, and removed */
        self::assertSame(
            ['repair', 'docker start --attach 6acae5967be0', 'docker rm --force --volumes 6acae5967be0'],
            [
                array_last($this->client->getExecutedArguments()[0]),
                $this->client->getExecutedCommandLines()[1],
                $this->client->getExecutedCommandLines()[2]
            ]
        );
    }

    public function testMigrateThenRunsFlywayMigrateToCompletion(): void
    {
        /** @Given a Flyway container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then Flyway should have been created with the migrate command, run attached, and removed */
        self::assertSame(
            ['migrate', 'docker start --attach 6acae5967be0', 'docker rm --force --volumes 6acae5967be0'],
            [
                array_last($this->client->getExecutedArguments()[0]),
                $this->client->getExecutedCommandLines()[1],
                $this->client->getExecutedCommandLines()[2]
            ]
        );
    }

    public function testWithSchemaThenOverridesTheDetectedSchema(): void
    {
        /** @Given a running MySQL container with database "original" */
        $mySQLStarted = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'original',
            hostname: 'override-db'
        );

        /** @And a Flyway container with the MySQL source and a schema override */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->withSource(password: 'root', username: 'root', container: $mySQLStarted)
            ->withSchema(schema: 'custom_schema');

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then the overridden schema should come after the detected one, so it prevails */
        self::assertMatchesRegularExpression(
            '/FLYWAY_SCHEMAS=original .*FLYWAY_SCHEMAS=custom_schema/',
            implode(PHP_EOL, $this->client->getExecutedCommandLines())
        );
    }

    public function testWithSourceThenSetsTheDefaultHistoryTable(): void
    {
        /** @Given a running MySQL container */
        $mySQLStarted = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'test_db',
            hostname: 'table-db'
        );

        /** @And a Flyway container with the MySQL source */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->withSource(password: 'root', username: 'root', container: $mySQLStarted);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then the history table should default to "schema_history" */
        self::assertStringContainsString(
            'FLYWAY_TABLE=schema_history',
            implode(PHP_EOL, $this->client->getExecutedCommandLines())
        );
    }

    public function testValidateThenRunsFlywayValidateToCompletion(): void
    {
        /** @Given a Flyway container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When validate is called */
        $flyway->validate();

        /** @Then Flyway should have been created with the validate command, run attached, and removed */
        self::assertSame(
            ['validate', 'docker start --attach 6acae5967be0', 'docker rm --force --volumes 6acae5967be0'],
            [
                array_last($this->client->getExecutedArguments()[0]),
                $this->client->getExecutedCommandLines()[1],
                $this->client->getExecutedCommandLines()[2]
            ]
        );
    }

    public function testWithConnectRetriesThenSetsTheNumberOfRetries(): void
    {
        /** @Given a Flyway container with connection retries */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->withConnectRetries(retries: 10);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then the number of retries should be passed to Flyway */
        self::assertStringContainsString('FLYWAY_CONNECT_RETRIES=10', $this->client->getExecutedCommandLines()[0]);
    }

    public function testWithSourceThenConfiguresJdbcUrlAndCredentials(): void
    {
        /** @Given a running MySQL container */
        $mySQLStarted = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'app_database',
            hostname: 'source-db'
        );

        /** @And a Flyway container with the MySQL source */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->withSource(password: 'secret', username: 'admin', container: $mySQLStarted);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then the Flyway container should carry the JDBC URL and the credentials */
        $commandLines = implode(PHP_EOL, $this->client->getExecutedCommandLines());
        self::assertStringContainsString('FLYWAY_URL=jdbc:mysql://source-db:3306/app_database', $commandLines);
        self::assertStringContainsString('FLYWAY_USER=admin', $commandLines);
        self::assertStringContainsString('FLYWAY_PASSWORD=secret', $commandLines);
    }

    public function testWithMigrationsThenCopiesThemBeforeFlywayStarts(): void
    {
        /** @Given a Flyway container with migrations */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->withMigrations(pathOnHost: '/host/migrations');

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then Flyway should point at the migrations, copied after creation and before the attached start */
        self::assertContains(
            'FLYWAY_LOCATIONS=filesystem:/flyway/migrations',
            $this->client->getExecutedArguments()[0]
        );
        self::assertSame(
            'docker cp /host/migrations 6acae5967be0:/flyway/migrations',
            $this->client->getExecutedCommandLines()[1]
        );
    }

    public function testMigrateWhenFlywaySucceedsThenReturnsFlywayOutput(): void
    {
        /** @Given a Flyway container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And Flyway exits successfully after applying the migrations */
        $this->client->withDockerAttachedStartResponse(output: 'Successfully applied 2 migrations');

        /** @When migrate is called */
        $execution = $flyway->migrate();

        /** @Then the outcome should carry the Flyway output */
        self::assertSame('Successfully applied 2 migrations', $execution->getOutput());
    }

    public function testWithSourceThenWaitsForMySQLBeforeFlywayIsCreated(): void
    {
        /** @Given a running MySQL container, pinged once while it started */
        $mySQLStarted = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'app_database',
            hostname: 'ready-db'
        );

        /** @And a Flyway container with the MySQL source */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->withSource(password: 'root', username: 'root', container: $mySQLStarted);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then MySQL should have been pinged again right before the Flyway container was created */
        self::assertSame(
            ['docker exec ready-db env MYSQL_PWD=root mysqladmin ping -h 127.0.0.1', 'docker create'],
            [
                $this->client->getExecutedCommandLines()[5],
                implode(' ', array_slice($this->client->getExecutedArguments()[6], 0, 2))
            ]
        );
    }

    public function testMigrateWhenTimeoutIsGivenThenWaitsUpToThatTimeout(): void
    {
        /** @Given a Flyway container with a timeout of 45 seconds */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->withTimeout(timeout: Duration::fromSeconds(seconds: 45));

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then the wait for Flyway to exit should be bounded by the given timeout */
        self::assertSame([45], $this->client->getRequestedTimeouts());
    }

    public function testWithEnvironmentVariableThenPassesTheFlywaySetting(): void
    {
        /** @Given a Flyway container with a placeholder setting */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->withEnvironmentVariable(key: 'FLYWAY_PLACEHOLDERS_TENANT', value: 'acme');

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then the setting should be passed to Flyway */
        self::assertStringContainsString(
            'FLYWAY_PLACEHOLDERS_TENANT=acme',
            $this->client->getExecutedCommandLines()[0]
        );
    }

    public function testMigrateWhenFlywayFailsThenFlywayContainerIsRemoved(): void
    {
        /** @Given a Flyway container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And Flyway exits with a non-zero status */
        $this->client->withDockerAttachedStartResponse(output: 'ERROR: Unable to connect', isSuccessful: false);

        try {
            /** @When migrate is called */
            $flyway->migrate();
        } catch (ContainerExitUnsuccessful) {
            /** @Then the Flyway container should have been removed */
            self::assertSame(
                'docker rm --force --volumes 6acae5967be0',
                array_last($this->client->getExecutedCommandLines())
            );
        }
    }

    public function testWithSourceThenDetectsTheSchemaFromTheMySQLDatabase(): void
    {
        /** @Given a running MySQL container with database "products" */
        $mySQLStarted = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'products',
            hostname: 'schema-db'
        );

        /** @And a Flyway container with the MySQL source */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->withSource(password: 'root', username: 'root', container: $mySQLStarted);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then the target schema should be the MySQL database */
        self::assertStringContainsString(
            'FLYWAY_SCHEMAS=products',
            implode(PHP_EOL, $this->client->getExecutedCommandLines())
        );
    }

    public function testMigrateWhenTimeoutIsNotGivenThenWaitsUpToTwoMinutes(): void
    {
        /** @Given a Flyway container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then the wait for Flyway to exit should be bounded by 120 seconds */
        self::assertSame([120], $this->client->getRequestedTimeouts());
    }

    public function testWithTimeoutThenLeavesTheOriginalDefinitionUntouched(): void
    {
        /** @Given a Flyway container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And a copy of it with a timeout of 45 seconds */
        $flyway->withTimeout(timeout: Duration::fromSeconds(seconds: 45));

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When the original definition migrates */
        $flyway->migrate();

        /** @Then it should still wait up to the default timeout */
        self::assertSame([120], $this->client->getRequestedTimeouts());
    }

    public function testWithMigrationNamingValidationThenEnablesTheValidation(): void
    {
        /** @Given a Flyway container with migration naming validation */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->withMigrationNamingValidation();

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then the validation should be enabled in Flyway */
        self::assertStringContainsString(
            'FLYWAY_VALIDATE_MIGRATION_NAMING=true',
            $this->client->getExecutedCommandLines()[0]
        );
    }

    public function testMigrateThenFlywayContainerIsNotRemovedByDockerOnItsOwn(): void
    {
        /** @Given a Flyway container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then the Flyway container should be created without automatic removal, so its exit is observed */
        self::assertNotContains('--rm', $this->client->getExecutedArguments()[0]);
    }

    public function testMigrateWhenTwoFlywayContainersRunThenEachHasItsOwnName(): void
    {
        /** @Given a Flyway container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And another Flyway container from the same image, as a parallel test run would create */
        $parallelFlyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And the Docker daemon creates a Flyway container for each run */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the first Flyway container has migrated */
        $flyway->migrate();

        /** @When the other Flyway container migrates */
        $parallelFlyway->migrate();

        /** @Then each run should have created its container under a distinct name */
        self::assertNotSame(
            $this->client->getExecutedArguments()[0][5],
            $this->client->getExecutedArguments()[3][5]
        );
    }

    public function testMigrateWhenFlywayOutlastsTimeoutThenContainerWaitTimeout(): void
    {
        /** @Given a Flyway container with a timeout of 45 seconds */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->withTimeout(timeout: Duration::fromSeconds(seconds: 45));

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the attached run is interrupted when the timeout is reached */
        $this->client->withDockerAttachedStartException(
            exception: new DockerCommandExecutionFailed(
                reason: 'The process exceeded the timeout of 45 seconds.',
                command: 'docker start --attach 6acae5967be0'
            )
        );

        /** @Then a timeout exception carrying the given timeout should be thrown */
        $this->expectException(ContainerWaitTimeout::class);
        $this->expectExceptionMessageMatches('/^Container <\w+> did not exit within <45> seconds\.$/');

        /** @When migrate is called */
        $flyway->migrate();
    }

    public function testCleanAndMigrateThenEnablesCleanAndRunsCleanFollowedByMigrate(): void
    {
        /** @Given a Flyway container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When cleanAndMigrate is called */
        $flyway->cleanAndMigrate();

        /** @Then Flyway should be created with clean enabled and the clean and migrate commands */
        self::assertStringEndsWith(
            '--env FLYWAY_CLEAN_DISABLED=false flyway/flyway:12-alpine clean migrate',
            $this->client->getExecutedCommandLines()[0]
        );
    }

    public function testMigrateWhenFlywayOutlastsTimeoutThenFlywayContainerIsRemoved(): void
    {
        /** @Given a Flyway container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the attached run is interrupted when the timeout is reached */
        $this->client->withDockerAttachedStartException(
            exception: new DockerCommandExecutionFailed(reason: 'timeout', command: 'docker start')
        );

        try {
            /** @When migrate is called */
            $flyway->migrate();
        } catch (ContainerWaitTimeout) {
            /** @Then the Flyway container still running should have been removed */
            self::assertSame(
                'docker rm --force --volumes 6acae5967be0',
                array_last($this->client->getExecutedCommandLines())
            );
        }
    }

    public function testMigrateWhenFlywayCannotBeCreatedThenDockerCommandExecutionFailed(): void
    {
        /** @Given a Flyway container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And the Docker daemon fails to create the Flyway container */
        $this->client->withDockerCreateResponse(output: 'pull access denied', isSuccessful: false);

        /** @Then the failure reported by Docker should be thrown */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches('/Reason: pull access denied/');

        /** @When migrate is called */
        $flyway->migrate();
    }

    public function testMigrateWhenFlywayFailsThenContainerExitUnsuccessfulCarriesFlywayError(): void
    {
        /** @Given a Flyway container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client);

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And Flyway exits with a non-zero status after reporting the error */
        $this->client->withDockerAttachedStartResponse(
            output: "ERROR: Validate failed: Migrations invalid\n",
            isSuccessful: false
        );

        /** @Then an exception carrying the Flyway error should be thrown */
        $this->expectException(ContainerExitUnsuccessful::class);
        $this->expectExceptionMessageMatches(
            '/^Container <\w+> did not exit successfully\. Output: <ERROR: Validate failed: Migrations invalid>\.$/'
        );

        /** @When migrate is called */
        $flyway->migrate();
    }
}
