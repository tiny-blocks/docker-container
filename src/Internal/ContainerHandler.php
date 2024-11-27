<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal;

use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\Commands\Command;
use TinyBlocks\DockerContainer\Internal\Commands\DockerInspect;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Container\Models\Container;
use TinyBlocks\DockerContainer\Internal\Container\Models\ContainerId;

final readonly class ContainerHandler
{
    public function __construct(private Client $client)
    {
    }

    public function run(DockerRun $command): Container
    {
        $executionCompleted = $this->client->execute(command: $command);
        $id = ContainerId::from(value: $executionCompleted->getOutput());

        $data = $this->findContainerById(id: $id);
        $factory = new ContainerFactory(id: $id, container: $command->container, executionCompleted: $data);

        return $factory->build();
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

        $data = $this->findContainerById(id: $id);
        $factory = new ContainerFactory(id: $id, container: $command->container, executionCompleted: $data);

        return $factory->build();
    }

    public function execute(Command $command): ExecutionCompleted
    {
        return $this->client->execute(command: $command);
    }

    private function findContainerById(ContainerId $id): ExecutionCompleted
    {
        $dockerInspect = DockerInspect::from(id: $id);

        return $this->client->execute(command: $dockerInspect);
    }
}
