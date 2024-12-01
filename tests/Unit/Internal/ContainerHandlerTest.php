<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal;

use PHPUnit\Framework\TestCase;
use Test\Unit\ClientMock;
use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Commands\Options\CommandOptions;
use TinyBlocks\DockerContainer\Internal\Commands\Options\EnvironmentVariableOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\NetworkOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\SimpleCommandOption;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Container;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerContainerNotFound;

final class ContainerHandlerTest extends TestCase
{
    private Client $client;

    private ContainerHandler $handler;

    protected function setUp(): void
    {
        $this->client = new ClientMock();
        $this->handler = new ContainerHandler(client: $this->client);
    }

    public function testShouldRunContainerSuccessfully(): void
    {
        /** @Given a DockerRun command */
        $command = DockerRun::from(
            commands: [],
            container: Container::create(name: 'alpine', image: 'alpine:latest'),
            network: NetworkOption::from(name: 'bridge'),
            detached: SimpleCommandOption::DETACH,
            autoRemove: SimpleCommandOption::REMOVE,
            environmentVariables: CommandOptions::createFromOptions(
                commandOption: EnvironmentVariableOption::from(key: 'PASSWORD', value: 'root')
            )
        );

        /** @And the DockerRun command was executed and returned the container ID */
        $this->client->withDockerRunResponse(data: '6acae5967be05d8441b4109eea3e4dec5e775068a2a99d95808afb21b2e0a2c8');

        /** @And the DockerInspect command was executed and returned the container's details */
        $this->client->withDockerInspectResponse(data: [
            'Id'              => '6acae5967be05d8441b4109eea3e4dec5e775068a2a99d95808afb21b2e0a2c8',
            'Name'            => '/alpine',
            'Config'          => [
                'Hostname'     => 'alpine',
                'ExposedPorts' => [],
                'Env'          => [
                    'PASSWORD=root'
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

        /** @When running the container */
        $container = $this->handler->run(command: $command);

        /** @Then the container should be created with the correct details */
        self::assertSame('root', $container->environmentVariables->getValueBy(key: 'PASSWORD'));
        self::assertSame('alpine', $container->name->value);
        self::assertSame('alpine', $container->address->getHostname());
        self::assertSame('172.22.0.2', $container->address->getIp());
        self::assertSame('6acae5967be0', $container->id->value);
        self::assertSame('alpine:latest', $container->image->name);
    }

    public function testShouldFindContainerSuccessfully(): void
    {
        /** @Given a DockerList command */
        $command = DockerList::from(container: Container::create(name: 'alpine', image: 'alpine:latest'));

        /** @And the DockerList command was executed and returned the container ID */
        $this->client->withDockerListResponse(data: '6acae5967be05d8441b4109eea3e4dec5e775068a2a99d95808afb21b2e0a2c8');

        /** @And the DockerInspect command was executed and returned the container details */
        $this->client->withDockerInspectResponse(data: [
            'Id'              => '6acae5967be05d8441b4109eea3e4dec5e775068a2a99d95808afb21b2e0a2c8',
            'Name'            => '/alpine',
            'Config'          => [
                'Hostname'     => 'alpine',
                'ExposedPorts' => [],
                'Env'          => [
                    'PASSWORD=root'
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

        /** @When finding the container */
        $container = $this->handler->findBy(command: $command);

        /** @Then the container should be returned with the correct details */
        self::assertSame('root', $container->environmentVariables->getValueBy(key: 'PASSWORD'));
        self::assertSame('alpine', $container->name->value);
        self::assertSame('alpine', $container->address->getHostname());
        self::assertSame('172.22.0.2', $container->address->getIp());
        self::assertSame('6acae5967be0', $container->id->value);
        self::assertSame('alpine:latest', $container->image->name);
    }

    public function testShouldReturnEmptyContainerWhenNotFound(): void
    {
        /** @Given a DockerList command */
        $command = DockerList::from(container: Container::create(name: 'alpine', image: 'alpine:latest'));

        /** @And the DockerList command was executed and returned the container ID */
        $this->client->withDockerListResponse(data: '');

        /** @When finding the container */
        $container = $this->handler->findBy(command: $command);

        /** @Then the container should be returned with the correct details */
        self::assertNull($container->id);
        self::assertSame('alpine', $container->name->value);
        self::assertSame('localhost', $container->address->getHostname());
        self::assertSame('127.0.0.1', $container->address->getIp());
        self::assertSame('alpine:latest', $container->image->name);
    }

    public function testShouldExecuteCommandSuccessfully(): void
    {
        /** @Given a DockerList command */
        $command = DockerList::from(container: Container::create(name: 'alpine', image: 'alpine:latest'));

        /** @And the DockerList command was executed and returned the container ID */
        $this->client->withDockerListResponse(data: '6acae5967be05d8441b4109eea3e4dec5e775068a2a99d95808afb21b2e0a2c8');

        /** @When executing the DockerList command */
        $executionCompleted = $this->handler->execute(command: $command);

        /** @Then the execution should be successful and return the correct output */
        self::assertTrue($executionCompleted->isSuccessful());
        self::assertSame(
            '6acae5967be05d8441b4109eea3e4dec5e775068a2a99d95808afb21b2e0a2c8',
            $executionCompleted->getOutput()
        );
    }

    public function testExceptionWhenDockerContainerNotFound(): void
    {
        /** @Given a DockerRun command */
        $command = DockerRun::from(
            commands: [],
            container: Container::create(name: 'alpine', image: 'alpine:latest'),
            network: NetworkOption::from(name: 'bridge'),
            detached: SimpleCommandOption::DETACH,
            autoRemove: SimpleCommandOption::REMOVE,
            environmentVariables: CommandOptions::createFromOptions(
                commandOption: EnvironmentVariableOption::from(key: 'PASSWORD', value: 'root')
            )
        );

        /** @And the DockerRun command was executed and returned the container ID */
        $this->client->withDockerRunResponse(data: '6acae5967be05d8441b4109eea3e4dec5e775068a2a99d95808afb21b2e0a2c8');

        /** @And the DockerInspect command was executed but returned an empty response */
        $this->client->withDockerInspectResponse(data: []);

        /** @Then an exception indicating that the Docker container was not found should be thrown */
        $this->expectException(DockerContainerNotFound::class);
        $this->expectExceptionMessage('Docker container with name <alpine> was not found.');

        /** @When running the container */
        $this->handler->run(command: $command);
    }
}
