<?php

declare(strict_types=1);

namespace Test\Integration;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\GenericContainer;
use TinyBlocks\DockerContainer\MySQLContainer;
use TinyBlocks\DockerContainer\Waits\Conditions\MySQL\MySQLReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;

final class DockerContainerTest extends TestCase
{
    private const string ROOT = 'root';
    private const string DATABASE = 'test_adm';

    public function testContainerRunsAndStopsSuccessfully(): void
    {
        /** @Given a container is configured */
        $container = GenericContainer::from(image: 'gustavofreze/php:8.3-fpm')
            ->withNetwork(name: 'tiny-blocks')
            ->withPortMapping(portOnHost: 9000, portOnContainer: 9000);

        /** @When the container is running */
        $container = $container->run();

        /** @Then the container should have the expected data */
        $address = $container->getAddress();

        self::assertSame(9000, $address->getPorts()->firstExposedPort());
        self::assertNotSame('127.0.0.1', $address->getIp());
        self::assertNotEmpty($address->getHostname());

        /** @And the container should be stopped successfully */
        $actual = $container->stop();

        /** @Then the stop operation should be successful, and no output should be returned */
        self::assertTrue($actual->isSuccessful());
        self::assertNotEmpty($actual->getOutput());
    }

    public function testMultipleContainersAreRunSuccessfully(): void
    {
        /** @Given a MySQL container is set up with a database */
        $mySQLContainer = MySQLContainer::from(image: 'mysql:8.1', name: 'test-database')
            ->withNetwork(name: 'tiny-blocks')
            ->withTimezone(timezone: 'America/Sao_Paulo')
            ->withUsername(user: self::ROOT)
            ->withPassword(password: self::ROOT)
            ->withDatabase(database: self::DATABASE)
            ->withPortMapping(portOnHost: 3306, portOnContainer: 3306)
            ->withRootPassword(rootPassword: self::ROOT)
            ->withVolumeMapping(pathOnHost: '/var/lib/mysql', pathOnContainer: '/var/lib/mysql')
            ->withoutAutoRemove()
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

        $flywayContainer = GenericContainer::from(image: 'flyway/flyway:11.0.0')
            ->withWait(
                wait: ContainerWaitForDependency::untilReady(
                    condition: MySQLReady::from(
                        container: $mySQLContainer
                    )
                )
            )
            ->withNetwork(name: 'tiny-blocks')
            ->copyToContainer(pathOnHost: '/migrations', pathOnContainer: '/flyway/sql')
            ->withVolumeMapping(pathOnHost: '/migrations', pathOnContainer: '/flyway/sql')
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
        $flywayContainer = $flywayContainer->run(commandsOnRun: ['-connectRetries=15', 'clean', 'migrate']);

        self::assertNotEmpty($flywayContainer->getName());

        /** @Then the Flyway container should execute the migrations successfully */
        $actual = MySQLRepository::connectFrom(container: $mySQLContainer)->allRecordsFrom(table: 'xpto');

        self::assertCount(10, $actual);
    }

    public function testRunCalledTwiceForSameContainerDoesNotStartTwice(): void
    {
        /** @Given a container is configured */
        $container = GenericContainer::from(image: 'gustavofreze/php:8.3-fpm', name: 'test-container')
            ->withNetwork(name: 'tiny-blocks')
            ->withPortMapping(portOnHost: 9001, portOnContainer: 9001);

        /** @When the container is started for the first time */
        $firstRun = $container->runIfNotExists();

        /** @Then the container should be successfully started */
        self::assertNotEmpty($firstRun->getId());

        /** @And when the same container is started again */
        $secondRun = GenericContainer::from(image: 'gustavofreze/php:8.3-fpm', name: 'test-container')
            ->withNetwork(name: 'tiny-blocks')
            ->withPortMapping(portOnHost: 9001, portOnContainer: 9001)
            ->withoutAutoRemove()
            ->runIfNotExists();

        /** @Then the container should not be restarted, and its ID should remain the same */
        self::assertSame($firstRun->getId(), $secondRun->getId());
    }
}
