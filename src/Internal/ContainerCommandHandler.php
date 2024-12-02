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
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

final readonly class ContainerCommandHandler implements CommandHandler
{
    private ContainerFactory $containerFactory;

    public function __construct(private Client $client)
    {
        $this->containerFactory = new ContainerFactory(client: $client);
    }

    public function run(DockerRun $dockerRun): Container
    {
        $executionCompleted = $this->client->execute(command: $dockerRun);

        if (!$executionCompleted->isSuccessful()) {
            throw DockerCommandExecutionFailed::fromCommand(command: $dockerRun, execution: $executionCompleted);
        }

        $id = ContainerId::from(value: $executionCompleted->getOutput());

        return $this->containerFactory->buildFrom(id: $id, container: $dockerRun->container);
    }

    public function findBy(DockerList $dockerList): Container
    {
        $container = $dockerList->container;
        $executionCompleted = $this->client->execute(command: $dockerList);

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
