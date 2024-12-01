<?php

declare(strict_types=1);

namespace Test\Integration;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\GenericDockerContainer;
use TinyBlocks\DockerContainer\MySQLDockerContainer;
use TinyBlocks\DockerContainer\Waits\Conditions\MySQL\MySQLReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForTime;

final class DockerContainerTest extends TestCase
{
    private const string ROOT = 'root';
    private const string DATABASE = 'test_adm';

    public function testMultipleContainersAreRunSuccessfully(): void
    {
        /** @Given a MySQL container is set up with a database */
        $mySQLContainer = MySQLDockerContainer::from(image: 'mysql:8.1', name: 'test-database')
            ->withNetwork(name: 'tiny-blocks')
            ->withTimezone(timezone: 'America/Sao_Paulo')
            ->withUsername(user: self::ROOT)
            ->withPassword(password: self::ROOT)
            ->withDatabase(database: self::DATABASE)
            ->withPortMapping(portOnHost: 3306, portOnContainer: 3306)
            ->withRootPassword(rootPassword: self::ROOT)
            ->withGrantedHosts()
            ->withoutAutoRemove()
            ->withVolumeMapping(pathOnHost: '/var/lib/mysql', pathOnContainer: '/var/lib/mysql')
            ->runIfNotExists();

        /** @And the MySQL container is running */
        $environmentVariables = $mySQLContainer->getEnvironmentVariables();
        $database = $environmentVariables->getValueBy(key: 'MYSQL_DATABASE');
        $username = $environmentVariables->getValueBy(key: 'MYSQL_USER');
        $password = $environmentVariables->getValueBy(key: 'MYSQL_PASSWORD');
        $address = $mySQLContainer->getAddress();
        $port = $address->getPorts()->firstExposedPort();

        self::assertSame('test-database', $mySQLContainer->getName());
        self::assertSame(3306, $port);
        self::assertSame(self::DATABASE, $database);

        /** @Given a Flyway container is configured to perform database migrations */
        $jdbcUrl = $mySQLContainer->getJdbcUrl(
            options: 'useUnicode=yes&characterEncoding=UTF-8&allowPublicKeyRetrieval=true&useSSL=false'
        );

        $flywayContainer = GenericDockerContainer::from(image: 'flyway/flyway:11.0.0')
            ->withNetwork(name: 'tiny-blocks')
            ->copyToContainer(pathOnHost: '/migrations', pathOnContainer: '/flyway/sql')
            ->withVolumeMapping(pathOnHost: '/migrations', pathOnContainer: '/flyway/sql')
            ->withWaitBeforeRun(
                wait: ContainerWaitForDependency::untilReady(
                    condition: MySQLReady::from(
                        container: $mySQLContainer
                    )
                )
            )
            ->withEnvironmentVariable(key: 'FLYWAY_URL', value: $jdbcUrl)
            ->withEnvironmentVariable(key: 'FLYWAY_USER', value: $username)
            ->withEnvironmentVariable(key: 'FLYWAY_TABLE', value: 'schema_history')
            ->withEnvironmentVariable(key: 'FLYWAY_SCHEMAS', value: $database)
            ->withEnvironmentVariable(key: 'FLYWAY_EDITION', value: 'community')
            ->withEnvironmentVariable(key: 'FLYWAY_PASSWORD', value: $password)
            ->withEnvironmentVariable(key: 'FLYWAY_LOCATIONS', value: 'filesystem:/flyway/sql')
            ->withEnvironmentVariable(key: 'FLYWAY_CLEAN_DISABLED', value: 'false')
            ->withEnvironmentVariable(key: 'FLYWAY_VALIDATE_MIGRATION_NAMING', value: 'true');

        /** @When the Flyway container runs the migration commands */
        $flywayContainer = $flywayContainer->run(
            commands: ['-connectRetries=15', 'clean', 'migrate'],
            waitAfterStarted: ContainerWaitForTime::forSeconds(seconds: 5)
        );

        self::assertNotEmpty($flywayContainer->getName());

        /** @Then the Flyway container should execute the migrations successfully */
        $actual = MySQLRepository::connectFrom(container: $mySQLContainer)->allRecordsFrom(table: 'xpto');

        self::assertCount(10, $actual);
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

        /** @Then the container should be successfully started */
        self::assertSame('123', $firstRun->getEnvironmentVariables()->getValueBy(key: 'TEST'));

        /** @And when the same container is started again */
        $secondRun = GenericDockerContainer::from(image: 'php:fpm-alpine', name: 'test-container')
            ->runIfNotExists();

        /** @Then the container should not be restarted */
        self::assertSame($firstRun->getId(), $secondRun->getId());
        self::assertSame($firstRun->getName(), $secondRun->getName());
        self::assertEquals($firstRun->getAddress(), $secondRun->getAddress());
        self::assertEquals($firstRun->getEnvironmentVariables(), $secondRun->getEnvironmentVariables());

        /** @And when the container is stopped */
        $actual = $firstRun->stop();

        /** @Then the stop operation should be successful */
        self::assertTrue($actual->isSuccessful());
    }
}
