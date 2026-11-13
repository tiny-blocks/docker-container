<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use Throwable;
use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRemove;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;

final readonly class ContainerStartup
{
    public function __construct(
        private ContainerWaits $waits,
        private DockerClient $client,
        private ContainerDefinition $definition
    ) {
    }

    public function start(array $commands): Started
    {
        $id = $this->launch(commands: $commands);

        try {
            return $this->handOver(id: $id);
        } catch (Throwable $failure) {
            $this->client->execute(command: DockerRemove::from(id: $id));

            throw $failure;
        }
    }

    public function launch(array $commands): ContainerId
    {
        $this->waits->waitBeforeRun();

        return new ContainerLauncher(client: $this->client)->start(commands: $commands, definition: $this->definition);
    }

    public function handOver(ContainerId $id): Started
    {
        $containerStarted = new ContainerLookup(client: $this->client)->byId(id: $id, name: $this->definition->name);
        $this->waits->waitAfterStarted(containerStarted: $containerStarted);

        return $containerStarted;
    }
}
