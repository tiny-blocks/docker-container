<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\CommandHandler\CommandHandler;
use TinyBlocks\DockerContainer\Internal\Commands\DockerInspect;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerContainerNotFound;

final readonly class ContainerLookup
{
    public function __construct(private Client $client, private ShutdownHook $shutdownHook)
    {
    }

    public function byId(
        ContainerId $id,
        ContainerDefinition $definition,
        CommandHandler $commandHandler
    ): ContainerStarted {
        $dockerInspect = DockerInspect::from(id: $id);
        $executionCompleted = $this->client->execute(command: $dockerInspect);

        $inspectPayload = json_decode($executionCompleted->getOutput(), true);

        if (!is_array($inspectPayload) || empty($inspectPayload[0]) || !is_array($inspectPayload[0])) {
            throw new DockerContainerNotFound(name: $definition->name);
        }

        $inspection = ContainerInspection::from(inspectResult: $inspectPayload[0]);

        return new Started(
            id: $id,
            name: $definition->name,
            address: $inspection->toAddress(),
            shutdownHook: $this->shutdownHook,
            commandHandler: $commandHandler,
            environmentVariables: $inspection->toEnvironmentVariables()
        );
    }
}
