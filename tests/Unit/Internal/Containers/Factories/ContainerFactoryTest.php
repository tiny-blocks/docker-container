<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Factories;

use PHPUnit\Framework\TestCase;
use Test\Unit\ClientMock;
use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Address\Address;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Container;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Environment\EnvironmentVariables;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Image;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Name;

final class ContainerFactoryTest extends TestCase
{
    private Client $client;

    private ContainerFactory $factory;

    protected function setUp(): void
    {
        $this->client = new ClientMock();
        $this->factory = new ContainerFactory(client: $this->client);
    }

    public function testShouldBuildContainerFromDockerInspect(): void
    {
        /** @Given a response containing the details of a container */
        $this->client->withResponse(response: [
            'Id'              => 'abc123abc123',
            'Name'            => '/my-container',
            'Config'          => [
                'Hostname'     => 'my-container-host',
                'ExposedPorts' => [
                    '3306/tcp' => [],
                    '8080/tcp' => []
                ],
                'Env'          => [
                    'MYSQL_USER=root',
                    'MYSQL_PASSWORD=secret',
                    'MYSQL_DATABASE=test_db'
                ]
            ],
            'NetworkSettings' => [
                'Networks' => [
                    'bridge' => [
                        'IPAddress' => '172.22.0.2'
                    ]
                ]
            ]
        ]);

        /** @And a container with basic details but no runtime information */
        $originalContainer = Container::from(
            id: null,
            name: Name::from(value: 'my-container'),
            image: Image::from(image: 'my-image'),
            address: Address::create(),
            environmentVariables: EnvironmentVariables::createFromEmpty()
        );

        /** @When building the container using the factory */
        $actual = $this->factory->buildFrom(
            id: ContainerId::from(value: 'abc123abc123'),
            container: $originalContainer
        );

        /** @Then the container should have all runtime information resolved */
        self::assertSame('root', $actual->environmentVariables->getValueBy(key: 'MYSQL_USER'));
        self::assertSame('secret', $actual->environmentVariables->getValueBy(key: 'MYSQL_PASSWORD'));
        self::assertSame('test_db', $actual->environmentVariables->getValueBy(key: 'MYSQL_DATABASE'));
        self::assertSame('172.22.0.2', $actual->address->getIp());
        self::assertSame([3306, 8080], $actual->address->getPorts()->exposedPorts());
        self::assertSame('abc123abc123', $actual->id->value);
        self::assertSame('my-container', $actual->name->value);
        self::assertSame('my-container-host', $actual->address->getHostname());
    }

    public function testShouldHandleEmptyKeysInDockerInspectResponse(): void
    {
        /** @Given a response containing the details of a container with empty values */
        $this->client->withResponse(response: [
            'Id'              => 'abc123abc123',
            'Name'            => '/my-container',
            'Config'          => [
                'Hostname'     => '',
                'ExposedPorts' => [],
                'Env'          => []
            ],
            'NetworkSettings' => [
                'Networks' => [
                    'bridge' => [
                        'IPAddress' => ''
                    ]
                ]
            ]
        ]);

        /** @And a container with basic details but no runtime information */
        $originalContainer = Container::from(
            id: null,
            name: Name::from(value: 'my-container'),
            image: Image::from(image: 'my-image'),
            address: Address::create(),
            environmentVariables: EnvironmentVariables::createFromEmpty()
        );

        /** @When building the container using the factory */
        $actual = $this->factory->buildFrom(
            id: ContainerId::from(value: 'abc123abc123'),
            container: $originalContainer
        );

        /** @Then the container should have all runtime information resolved to default values */
        self::assertSame([], $actual->address->getPorts()->exposedPorts());
        self::assertSame([], $actual->environmentVariables->toArray());
        self::assertSame('localhost', $actual->address->getHostname());
        self::assertSame('127.0.0.1', $actual->address->getIp());
        self::assertSame('abc123abc123', $actual->id->value);
        self::assertSame('my-container', $actual->name->value);
    }
}
