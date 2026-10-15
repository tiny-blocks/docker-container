<?php

declare(strict_types=1);

namespace Test\Integration;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Exceptions\ContainerExitUnsuccessful;
use TinyBlocks\DockerContainer\FlywayDockerContainer;
use TinyBlocks\DockerContainer\GenericDockerContainer;
use TinyBlocks\DockerContainer\MySQL\MySQLContainerStarted;
use TinyBlocks\DockerContainer\MySQLDockerContainer;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForTime;
use TinyBlocks\Time\Duration;

final class DockerContainerTest extends TestCase
{
    private const string ROOT = 'xpto';
    private const string DATABASE = 'test_adm';

    private static MySQLContainerStarted $mySQLStarted;

    public static function setUpBeforeClass(): void
    {
        $template = 'test-database-%s';

        self::$mySQLStarted = MySQLDockerContainer::from(image: 'mysql:8.4')
            ->withName(name: sprintf($template, gethostname()))
            ->withNetwork(name: 'tiny-blocks')
            ->withDatabase(database: self::DATABASE)
            ->withPassword(password: self::ROOT)
            ->withTimezone(timezone: 'America/Sao_Paulo')
            ->withUsername(user: self::ROOT)
            ->withPortMapping(portOnHost: 0, portOnContainer: 3306)
            ->withGrantedHosts()
            ->withRootPassword(rootPassword: self::ROOT)
            ->withReadinessTimeout(timeout: Duration::fromSeconds(seconds: 60))
            ->runIfNotExists();
    }

    public function testMySQLWhenStartedThenAcceptsConnectionsToItsDatabase(): void
    {
        /** @Given the MySQL container shared by this test run */
        $environmentVariables = self::$mySQLStarted->getEnvironmentVariables();

        /** @When the address of the container is read */
        $address = self::$mySQLStarted->getAddress();

        /** @Then the container should expose MySQL and carry the configured database */
        self::assertSame(3306, $address->getPorts()->firstExposedPort());
        self::assertSame(self::DATABASE, $environmentVariables->getValueBy(key: 'MYSQL_DATABASE'));
    }

    public function testRunToCompletionWhenCommandSucceedsThenReturnsItsOutput(): void
    {
        /** @Given a container whose command prints a line and exits successfully */
        $container = GenericDockerContainer::from(image: 'php:fpm-alpine');

        /** @When the container runs to completion */
        $execution = $container->runToCompletion(
            timeout: Duration::fromSeconds(seconds: 60),
            commands: ['sh', '-c', 'echo completed']
        );

        /** @Then the outcome should carry what the container wrote */
        self::assertSame('completed', trim($execution->getOutput()));
    }

    public function testRunIfNotExistsWhenCalledTwiceThenTheSameContainerIsReused(): void
    {
        /** @Given a container name unique to this test run */
        $name = sprintf('test-container-%s', gethostname());

        /** @And a container that sleeps once started */
        $container = GenericDockerContainer::from(image: 'php:fpm-alpine')
            ->withName(name: $name)
            ->withNetwork(name: 'tiny-blocks')
            ->withWaitBeforeRun(wait: ContainerWaitForTime::forDuration(duration: Duration::fromSeconds(seconds: 1)))
            ->withEnvironmentVariable(key: 'TEST', value: '123');

        /** @And the container started for the first time */
        $firstRun = $container->runIfNotExists();

        /** @When the same container is asked for again */
        $secondRun = GenericDockerContainer::from(image: 'php:fpm-alpine')
            ->withName(name: $name)
            ->runIfNotExists();

        /** @Then the first run should be reused rather than restarted */
        self::assertTrue($secondRun->wasReused());
        self::assertSame($firstRun->getId(), $secondRun->getId());
        self::assertSame('123', $secondRun->getEnvironmentVariables()->getValueBy(key: 'TEST'));
    }

    public function testRunWhenFilesAreCopiedThenTheyExistBeforeTheContainerStarts(): void
    {
        /** @Given a directory holding a marker file */
        $template = '%s/Files';

        /** @And a container whose command exits at once unless the marker file is already present */
        $container = GenericDockerContainer::from(image: 'php:fpm-alpine')
            ->withNetwork(name: 'tiny-blocks')
            ->copyToContainer(pathOnHost: sprintf($template, __DIR__), pathOnContainer: '/copied');

        /** @And the container is started with that command */
        $started = $container->run(commands: ['sh', '-c', 'test -f /copied/marker.txt && sleep 60']);

        /** @And the container is removed when the test process exits */
        $started->stopOnShutdown();

        /** @When the marker file is looked up inside the running container */
        $lookup = $started->executeAfterStarted(commands: ['test', '-f', '/copied/marker.txt']);

        /** @Then the marker file should be in place */
        self::assertTrue($lookup->isSuccessful());
    }

    public function testCleanAndMigrateWhenMigrationIsSlowThenWaitsForFlywayToFinish(): void
    {
        /** @Given a migration directory whose migration sleeps longer than ten seconds */
        $template = '%s/Database/SlowMigrations';

        /** @And a Flyway container bound to the MySQL container with that slow migration */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine')
            ->withSource(password: self::ROOT, username: self::ROOT, container: self::$mySQLStarted)
            ->withNetwork(name: 'tiny-blocks')
            ->withMigrations(pathOnHost: sprintf($template, __DIR__))
            ->withConnectRetries(retries: 15);

        /** @When Flyway cleans the schema and runs the slow migration */
        $flyway->cleanAndMigrate();

        /** @Then the table created by the slow migration should already hold its rows */
        self::assertCount(
            1,
            MySQLRepository::connectFrom(container: self::$mySQLStarted)->allRecordsFrom(table: 'slow')
        );
    }

    public function testCleanAndMigrateWhenMigrationsAreGivenThenDatabaseIsPopulated(): void
    {
        /** @Given a migration directory */
        $template = '%s/Database/Migrations';

        /** @And a Flyway container bound to the MySQL container */
        $flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine')
            ->withSource(password: self::ROOT, username: self::ROOT, container: self::$mySQLStarted)
            ->withNetwork(name: 'tiny-blocks')
            ->withMigrations(pathOnHost: sprintf($template, __DIR__))
            ->withConnectRetries(retries: 15)
            ->withMigrationNamingValidation();

        /** @When Flyway cleans the schema and migrates it */
        $flyway->cleanAndMigrate();

        /** @Then the migrations should have populated the database */
        self::assertCount(
            10,
            MySQLRepository::connectFrom(container: self::$mySQLStarted)->allRecordsFrom(table: 'xpto')
        );
    }

    public function testRunToCompletionWhenCommandFailsThenContainerExitUnsuccessfulCarriesItsError(): void
    {
        /** @Given a container whose command reports an error and exits with a non-zero status */
        $container = GenericDockerContainer::from(image: 'php:fpm-alpine');

        /** @Then an exception carrying the error the container wrote should be thrown */
        $this->expectException(ContainerExitUnsuccessful::class);
        $this->expectExceptionMessageMatches('/Output: <migration failed>\.$/');

        /** @When the container runs to completion */
        $container->runToCompletion(
            timeout: Duration::fromSeconds(seconds: 60),
            commands: ['sh', '-c', 'echo "migration failed" >&2; exit 3']
        );
    }
}
