<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\Internal\Client\StrictExecutor;
use TinyBlocks\DockerContainer\Internal\Commands\DockerCopy;
use TinyBlocks\DockerContainer\Internal\Commands\DockerCreate;
use TinyBlocks\DockerContainer\Internal\Commands\DockerStart;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\CopyInstruction;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\Network;

final readonly class ContainerLauncher
{
    public function __construct(private DockerClient $client)
    {
    }

    public function start(array $commands, ContainerDefinition $definition): ContainerId
    {
        $id = $this->create(commands: $commands, definition: $definition);

        new StrictExecutor(client: $this->client)->execute(command: DockerStart::from(id: $id));

        return $id;
    }

    public function create(array $commands, ContainerDefinition $definition): ContainerId
    {
        $provisioner = new NetworkProvisioner(client: $this->client);
        $definition->networks->each(
            actions: static fn(Network $network) => $provisioner->provision(network: $network)
        );

        $dockerCreate = DockerCreate::from(commands: $commands, definition: $definition);
        $creation = new StrictExecutor(client: $this->client)->execute(command: $dockerCreate);
        $id = ContainerId::from(value: $creation->getOutput());

        $definition->copyInstructions->each(
            actions: fn(CopyInstruction $instruction) => $this->client->execute(
                command: DockerCopy::from(id: $id, instruction: $instruction)
            )
        );

        return $id;
    }
}
