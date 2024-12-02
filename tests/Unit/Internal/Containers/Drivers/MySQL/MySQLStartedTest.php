<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\MySQL;

use PHPUnit\Framework\TestCase;
use Test\Unit\CommandHandlerMock;
use TinyBlocks\DockerContainer\Internal\CommandHandler;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Address\Address;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Container;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Environment\EnvironmentVariables;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Image;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Name;

final class MySQLStartedTest extends TestCase
{
    private CommandHandler $commandHandler;

    protected function setUp(): void
    {
        $this->commandHandler = new CommandHandlerMock();
    }

    public function testJdbcUrlWithDefaultOptions(): void
    {
        /** @Given a container with default configuration */
        $container = Container::from(
            id: ContainerId::from(value: 'abc123abc123'),
            name: Name::from(value: 'mysql'),
            image: Image::from(image: 'mysql:latest'),
            address: Address::create(),
            environmentVariables: EnvironmentVariables::createFrom(elements: ['MYSQL_DATABASE' => 'test_db'])
        );

        /** @And a MySQLStarted instance is created with the container */
        $mysqlStarted = new MySQLStarted(container: $container, commandHandler: $this->commandHandler);

        /** @When calling getJdbcUrl without any additional options */
        $actual = $mysqlStarted->getJdbcUrl();

        /** @Then the returned JDBC URL should include default options */
        self::assertSame(
            'jdbc:mysql://localhost:3306/test_db?useSSL=false&useUnicode=yes&characterEncoding=UTF-8&allowPublicKeyRetrieval=true',
            $actual
        );
    }

    public function testJdbcUrlWithCustomOptions(): void
    {
        /** @Given a container with default configuration */
        $container = Container::from(
            id: ContainerId::from(value: 'abc123abc123'),
            name: Name::from(value: 'mysql'),
            image: Image::from(image: 'mysql:latest'),
            address: Address::create(),
            environmentVariables: EnvironmentVariables::createFrom(elements: ['MYSQL_DATABASE' => 'test_db'])
        );

        /** @And a MySQLStarted instance is created with the container */
        $mysqlStarted = new MySQLStarted(container: $container, commandHandler: $this->commandHandler);

        /** @When calling getJdbcUrl with custom options */
        $actual = $mysqlStarted->getJdbcUrl(options: ['connectTimeout' => '5000', 'useSSL' => 'true']);

        /** @Then the returned JDBC URL should include the custom options */
        self::assertSame('jdbc:mysql://localhost:3306/test_db?connectTimeout=5000&useSSL=true', $actual);
    }

    public function testJdbcUrlWithoutOptions(): void
    {
        /** @Given a container with default configuration */
        $container = Container::from(
            id: ContainerId::from(value: 'abc123abc123'),
            name: Name::from(value: 'mysql'),
            image: Image::from(image: 'mysql:latest'),
            address: Address::create(),
            environmentVariables: EnvironmentVariables::createFrom(elements: ['MYSQL_DATABASE' => 'test_db'])
        );

        /** @And a MySQLStarted instance is created with the container */
        $mysqlStarted = new MySQLStarted(container: $container, commandHandler: $this->commandHandler);

        /** @When calling getJdbcUrl with an empty options array */
        $actual = $mysqlStarted->getJdbcUrl(options: []);

        /** @Then the returned JDBC URL should not include any query string */
        self::assertSame('jdbc:mysql://localhost:3306/test_db', $actual);
    }
}
