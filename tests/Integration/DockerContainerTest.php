<?php

declare(strict_types=1);

namespace Test\Integration;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\FlywayDockerContainer;
use TinyBlocks\DockerContainer\GenericDockerContainer;
use TinyBlocks\DockerContainer\MySQLDockerContainer;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForTime;

final class DockerContainerTest extends TestCase
{
    private const string ROOT = 'xpto';
    private const string DATABASE = 'test_adm';

    public function testMultipleContainersAreRunSuccessfully(): void
    {
        /** @Given a MySQL container is configured */
        $mySQLContainer = MySQLDockerContainer::from(image: 'mysql:8.4', name: 'test-database')
            ->pullImage()
            ->withNetwork(name: 'tiny-blocks')
            ->withTimezone(timezone: 'America/Sao_Paulo')
            ->withUsername(user: self::ROOT)
            ->withPassword(password: self::ROOT)
            ->withDatabase(database: self::DATABASE)
            ->withPortMapping(portOnHost: 3306, portOnContainer: 3306)
            ->withRootPassword(rootPassword: self::ROOT)
            ->withGrantedHosts()
            ->withReadinessTimeout(timeoutInSeconds: 60);

        /** @And a Flyway container is configured with migrations (pull starts in parallel) */
        $flywayContainer = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine')
            ->pullImage()
            ->withNetwork(name: 'tiny-blocks')
            ->withMigrations(pathOnHost: '/test-adm-migrations')
            ->withCleanDisabled(disabled: false)
            ->withConnectRetries(retries: 15)
            ->withValidateMigrationNaming(enabled: true);

        /** @When the MySQL container is started */
        $mySQLStarted = $mySQLContainer->runIfNotExists();
        $mySQLStarted->stopOnShutdown();

        /** @Then the MySQL container should be running */
        $environmentVariables = $mySQLStarted->getEnvironmentVariables();
        $address = $mySQLStarted->getAddress();

        self::assertSame('test-database', $mySQLStarted->getName());
        self::assertSame(3306, $address->getPorts()->firstExposedPort());
        self::assertSame(self::DATABASE, $environmentVariables->getValueBy(key: 'MYSQL_DATABASE'));

        /** @And when Flyway runs migrations against the started MySQL container */
        $flywayStarted = $flywayContainer
            ->withSource(
                password: $environmentVariables->getValueBy(key: 'MYSQL_PASSWORD'),
                username: $environmentVariables->getValueBy(key: 'MYSQL_USER'),
                container: $mySQLStarted
            )
            ->cleanAndMigrate();

        /** @Then the migrations should have populated the database */
        self::assertNotEmpty($flywayStarted->getName());

        $records = MySQLRepository::connectFrom(container: $mySQLStarted)->allRecordsFrom(table: 'xpto');

        self::assertCount(10, $records);
    }

    public function testRunCalledTwiceForSameContainerDoesNotStartTwice(): void
    {
        /** @Given a container is configured */
        $container = GenericDockerContainer::from(image: 'php:fpm-alpine', name: 'test-container')
            ->withNetwork(name: 'tiny-blocks')
            ->withWaitBeforeRun(wait: ContainerWaitForTime::forSeconds(seconds: 1))
            ->withEnvironmentVariable(key: 'TEST', value: '123');

        /** @When the container is started for the first time */
        $firstRun = $container->runIfNotExists();
        $firstRun->stopOnShutdown();

        /** @Then the container should be successfully started */
        self::assertSame('123', $firstRun->getEnvironmentVariables()->getValueBy(key: 'TEST'));

        /** @And when the same container is started again */
        $secondRun = GenericDockerContainer::from(image: 'php:fpm-alpine', name: 'test-container')
            ->runIfNotExists();

        /** @Then the container should not be restarted */
        self::assertSame($firstRun->getId(), $secondRun->getId());
        self::assertSame($firstRun->getName(), $secondRun->getName());
        self::assertEquals($firstRun->getAddress(), $secondRun->getAddress());
        self::assertEquals(
            $firstRun->getEnvironmentVariables(),
            $secondRun->getEnvironmentVariables()
        );

        /** @And when the container is stopped */
        $stopped = $firstRun->stop();

        /** @Then the stop operation should be successful */
        self::assertTrue($stopped->isSuccessful());
    }
}
