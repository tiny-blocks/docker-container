<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal;

use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\Commands\Command;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Containers\Factories\ContainerFactory;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Container;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;

final readonly class ContainerHandler
{
    private ContainerFactory $containerFactory;

    public function __construct(private Client $client)
    {
        $this->containerFactory = new ContainerFactory(client: $client);
    }

    public function run(DockerRun $command): Container
    {
        $executionCompleted = $this->client->execute(command: $command);
        $id = ContainerId::from(value: $executionCompleted->getOutput());

        return $this->containerFactory->buildFrom(id: $id, container: $command->container);
    }

    public function findBy(DockerList $command): Container
    {
        $container = $command->container;
        $executionCompleted = $this->client->execute(command: $command);

        $output = $executionCompleted->getOutput();

        if (empty($output)) {
            return Container::create(name: $container->name->value, image: $container->image->name);
        }

        $id = ContainerId::from(value: $output);

        return $this->containerFactory->buildFrom(id: $id, container: $container);
    }

    public function execute(Command $command): ExecutionCompleted
    {
        return $this->client->execute(command: $command);
    }
}
