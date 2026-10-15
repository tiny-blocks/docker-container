<?php

declare(strict_types=1);

namespace Test\Unit;

use PHPUnit\Framework\TestCase;
use Test\Models\InspectResponseFixture;
use TinyBlocks\DockerContainer\FlywayDockerContainer;
use TinyBlocks\DockerContainer\MySQLDockerContainer;

final class FlywayContainerGatedTest extends TestCase
{
    private ClientMock $client;

    protected function setUp(): void
    {
        $this->client = new ClientMock();
    }

    protected function tearDown(): void
    {
        putenv('RUN_MIGRATIONS');
    }

    public function testMigrateWhenVariableIsNotSetThenNothingRuns(): void
    {
        /** @Given the variable that enables the migrations is not set */
        putenv('RUN_MIGRATIONS');

        /** @And a Flyway container that runs only while the variable is enabled */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->onlyWhenEnabled(variable: 'RUN_MIGRATIONS');

        /** @When migrate is called */
        $execution = $flyway->migrate();

        /** @Then nothing should have run, and no Docker command should have been issued */
        self::assertSame([null, []], [$execution, $this->client->getExecutedArguments()]);
    }

    public function testRepairWhenVariableIsDisabledThenNothingRuns(): void
    {
        /** @Given the variable that enables the migrations is set to 0 */
        putenv('RUN_MIGRATIONS=0');

        /** @And a Flyway container that runs only while the variable is enabled */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->onlyWhenEnabled(variable: 'RUN_MIGRATIONS');

        /** @When repair is called */
        $execution = $flyway->repair();

        /** @Then nothing should have run, and no Docker command should have been issued */
        self::assertSame([null, []], [$execution, $this->client->getExecutedArguments()]);
    }

    public function testValidateWhenVariableIsDisabledThenNothingRuns(): void
    {
        /** @Given the variable that enables the migrations is set to 0 */
        putenv('RUN_MIGRATIONS=0');

        /** @And a Flyway container that runs only while the variable is enabled */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->onlyWhenEnabled(variable: 'RUN_MIGRATIONS');

        /** @When validate is called */
        $execution = $flyway->validate();

        /** @Then nothing should have run, and no Docker command should have been issued */
        self::assertSame([null, []], [$execution, $this->client->getExecutedArguments()]);
    }

    public function testRepairWhenVariableIsEnabledThenRunsFlywayRepair(): void
    {
        /** @Given the variable that enables the migrations is set to 1 */
        putenv('RUN_MIGRATIONS=1');

        /** @And a Flyway container that runs only while the variable is enabled */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->onlyWhenEnabled(variable: 'RUN_MIGRATIONS');

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And Flyway exits successfully */
        $this->client->withDockerAttachedStartResponse(output: 'Successfully repaired schema history table');

        /** @When repair is called */
        $execution = $flyway->repair();

        /** @Then Flyway should have run the repair command and returned its output */
        self::assertStringEndsWith('flyway/flyway:12-alpine repair', $this->client->getExecutedCommandLines()[0]);
        self::assertSame('Successfully repaired schema history table', $execution?->getOutput());
    }

    public function testMigrateWhenVariableIsEnabledThenRunsFlywayMigrate(): void
    {
        /** @Given the variable that enables the migrations is set to 1 */
        putenv('RUN_MIGRATIONS=1');

        /** @And a Flyway container that runs only while the variable is enabled */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->onlyWhenEnabled(variable: 'RUN_MIGRATIONS');

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And Flyway exits successfully */
        $this->client->withDockerAttachedStartResponse(output: 'Successfully applied 2 migrations');

        /** @When migrate is called */
        $execution = $flyway->migrate();

        /** @Then Flyway should have run the migrate command and returned its output */
        self::assertStringEndsWith('flyway/flyway:12-alpine migrate', $this->client->getExecutedCommandLines()[0]);
        self::assertSame('Successfully applied 2 migrations', $execution?->getOutput());
    }

    public function testValidateWhenVariableIsEnabledThenRunsFlywayValidate(): void
    {
        /** @Given the variable that enables the migrations is set to 1 */
        putenv('RUN_MIGRATIONS=1');

        /** @And a Flyway container that runs only while the variable is enabled */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->onlyWhenEnabled(variable: 'RUN_MIGRATIONS');

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And Flyway exits successfully */
        $this->client->withDockerAttachedStartResponse(output: 'Successfully validated 2 migrations');

        /** @When validate is called */
        $execution = $flyway->validate();

        /** @Then Flyway should have run the validate command and returned its output */
        self::assertStringEndsWith('flyway/flyway:12-alpine validate', $this->client->getExecutedCommandLines()[0]);
        self::assertSame('Successfully validated 2 migrations', $execution?->getOutput());
    }

    public function testMigrateWhenVariableIsOneWithLeadingZeroThenNothingRuns(): void
    {
        /** @Given the variable that enables the migrations is set to 01, which is not 1 */
        putenv('RUN_MIGRATIONS=01');

        /** @And a Flyway container that runs only while the variable is enabled */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->onlyWhenEnabled(variable: 'RUN_MIGRATIONS');

        /** @When migrate is called */
        $execution = $flyway->migrate();

        /** @Then nothing should have run, and no Docker command should have been issued */
        self::assertSame([null, []], [$execution, $this->client->getExecutedArguments()]);
    }

    public function testCleanAndMigrateWhenVariableIsDisabledThenNeitherMySQLNorFlywayRuns(): void
    {
        /** @Given the variable that enables the migrations is set to 0 */
        putenv('RUN_MIGRATIONS=0');

        /** @And a MySQL container definition */
        $mySQL = MySQLDockerContainer::from(image: 'mysql:8.4', client: $this->client)->withName(name: 'skipped-db');

        /** @And a Flyway container with the MySQL source that runs only while the variable is enabled */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->withSource(container: $mySQL)
            ->onlyWhenEnabled(variable: 'RUN_MIGRATIONS');

        /** @When cleanAndMigrate is called */
        $execution = $flyway->cleanAndMigrate();

        /** @Then nothing should have run, and no Docker command should have been issued for MySQL or Flyway */
        self::assertSame([null, []], [$execution, $this->client->getExecutedArguments()]);
    }

    public function testCleanAndMigrateWhenVariableIsEnabledThenRunsCleanFollowedByMigrate(): void
    {
        /** @Given the variable that enables the migrations is set to 1 */
        putenv('RUN_MIGRATIONS=1');

        /** @And a Flyway container that runs only while the variable is enabled */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->onlyWhenEnabled(variable: 'RUN_MIGRATIONS');

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And Flyway exits successfully */
        $this->client->withDockerAttachedStartResponse(output: 'Successfully applied 2 migrations');

        /** @When cleanAndMigrate is called */
        $execution = $flyway->cleanAndMigrate();

        /** @Then Flyway should have run the clean and migrate commands and returned its output */
        self::assertStringEndsWith(
            'flyway/flyway:12-alpine clean migrate',
            $this->client->getExecutedCommandLines()[0]
        );
        self::assertSame('Successfully applied 2 migrations', $execution?->getOutput());
    }

    public function testMigrateWhenVariableIsEnabledAfterTheDefinitionThenRunsFlywayMigrate(): void
    {
        /** @Given a Flyway container that runs only while the variable is enabled */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine', client: $this->client)
            ->onlyWhenEnabled(variable: 'RUN_MIGRATIONS');

        /** @And the variable that enables the migrations is set to 1 afterwards */
        putenv('RUN_MIGRATIONS=1');

        /** @And the Docker daemon creates the Flyway container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When migrate is called */
        $flyway->migrate();

        /** @Then the variable should be read when the command is called, so Flyway should have run */
        self::assertStringEndsWith('flyway/flyway:12-alpine migrate', $this->client->getExecutedCommandLines()[0]);
    }
}
