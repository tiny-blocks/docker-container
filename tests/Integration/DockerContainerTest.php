<?php

declare(strict_types=1);

namespace Test\Integration;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\GenericContainer;
use TinyBlocks\DockerContainer\MySQLContainer;
use TinyBlocks\DockerContainer\NetworkDrivers;
use TinyBlocks\DockerContainer\Waits\Conditions\MySQL\MySQLReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;

final class DockerContainerTest extends TestCase
{
    private const string DATABASE = 'test_adm';
    private const string ROOT = 'root';

    public function estContainerRunsAndStopsSuccessfully(): void
    {
        /** @Given a container is configured */
        $container = GenericContainer::from(image: 'gustavofreze/php:8.3-fpm')
            ->withNetwork(driver: NetworkDrivers::HOST)
            ->withPortMapping(portOnHost: 9000, portOnContainer: 9000);

        /** @When the container is running */
        $container = $container->run();

        /** @Then the container should have the expected data */
        $address = $container->getAddress();

        self::assertSame('127.0.0.1', $address->getIp());
        self::assertSame(NetworkDrivers::HOST, $address->getDriver());

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
            ->withRootHost(host: '%')
            ->withUsername(user: self::ROOT)
            ->withPassword(password: self::ROOT)
            ->withDatabase(database: self::DATABASE)
            ->withPortMapping(portOnHost: 3306, portOnContainer: 3306)
            ->withRootPassword(rootPassword: self::ROOT)
            ->withVolumeMapping(pathOnHost: '/var/lib/mysql', pathOnContainer: '/var/lib/mysql')
            ->runIfNotExists();

        /** @And the MySQL container is running */
        $address = $mySQLContainer->getAddress();

        self::assertSame('test-database', $mySQLContainer->getName());
        self::assertSame(NetworkDrivers::BRIDGE, $address->getDriver());
        self::assertNotEmpty($address->getIp());
        self::assertNotEmpty($mySQLContainer->getId());

        /** @Given a Flyway container is configured to perform database migrations */
        $template = 'jdbc:mysql://%s:%s/%s?useUnicode=yes&characterEncoding=UTF-8&allowPublicKeyRetrieval=true&useSSL=false';
        $jdbcUrl = sprintf($template, $address->getIp(), $address->getPorts()->firstExposedPort(), self::DATABASE);

        $flywayContainer = GenericContainer::from(image: 'flyway/flyway:11.0.0')
            ->withWait(
                wait: ContainerWaitForDependency::untilReady(
                    condition: MySQLReady::from(
                        container: $mySQLContainer
                    )
                )
            )
            ->copyToContainer(pathOnHost: '/migrations', pathOnContainer: '/flyway/sql')
            ->withVolumeMapping(pathOnHost: '/migrations', pathOnContainer: '/flyway/sql')
            ->withEnvironmentVariable(key: 'FLYWAY_URL', value: $jdbcUrl)
            ->withEnvironmentVariable(key: 'FLYWAY_USER', value: 'root')
            ->withEnvironmentVariable(key: 'FLYWAY_TABLE', value: 'schema_history')
            ->withEnvironmentVariable(key: 'FLYWAY_SCHEMAS', value: self::DATABASE)
            ->withEnvironmentVariable(key: 'FLYWAY_EDITION', value: 'community')
            ->withEnvironmentVariable(key: 'FLYWAY_PASSWORD', value: self::ROOT)
            ->withEnvironmentVariable(key: 'FLYWAY_LOCATIONS', value: 'filesystem:/flyway/sql')
            ->withEnvironmentVariable(key: 'FLYWAY_CLEAN_DISABLED', value: 'false')
            ->withEnvironmentVariable(key: 'FLYWAY_VALIDATE_MIGRATION_NAMING', value: 'true');

        /** @When the Flyway container runs the migration commands */
        $flywayContainer = $flywayContainer->run(commandsOnRun: ['-connectRetries=15', 'clean', 'migrate']);

        self::assertSame(NetworkDrivers::BRIDGE, $flywayContainer->getAddress()->getDriver());
        self::assertNotEmpty($flywayContainer->getAddress()->getIp());
        self::assertNotEmpty($flywayContainer->getId());
        self::assertNotEmpty($flywayContainer->getName());

        /** @Then the Flyway container should execute the migrations successfully */
        $actual = MySQLRepository::connectFrom(container: $mySQLContainer)->allRecordsFrom(table: 'xpto');

        self::assertCount(10, $actual);
    }

    public function estRunCalledTwiceForSameContainerDoesNotStartTwice(): void
    {
        /** @Given a container is configured */
        $container = GenericContainer::from(image: 'gustavofreze/php:8.3-fpm', name: 'test-container')
            ->withNetwork(driver: NetworkDrivers::NONE)
            ->withPortMapping(portOnHost: 9001, portOnContainer: 9001);

        /** @When the container is started for the first time */
        $firstRun = $container->runIfNotExists();

        /** @Then the container should be successfully started */
        self::assertNotEmpty($firstRun->getId());

        /** @And when the same container is started again */
        $secondRun = GenericContainer::from(image: 'gustavofreze/php:8.3-fpm', name: 'test-container')
            ->withNetwork(driver: NetworkDrivers::NONE)
            ->withPortMapping(portOnHost: 9001, portOnContainer: 9001)
            ->withoutAutoRemove()
            ->runIfNotExists();

        /** @Then the container should not be restarted, and its ID should remain the same */
        self::assertSame($firstRun->getId(), $secondRun->getId());
    }
}
