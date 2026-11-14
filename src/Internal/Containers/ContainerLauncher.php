<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\Internal\Client\StrictExecutor;
use TinyBlocks\DockerContainer\Internal\Commands\DockerCopy;
use TinyBlocks\DockerContainer\Internal\Commands\DockerCreate;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRemove;
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
        $attempt = new ContainerStartAttempt(client: $this->client);
        $attempts = $definition->startAttempts();

        do {
            $attempts--;
            $id = $this->create(commands: $commands, definition: $definition);
            $failure = $attempt->failureOf(id: $id);
        } while (!is_null($failure) && $attempts > 0);

        if (!is_null($failure)) {
            throw $failure;
        }

        return $id;
    }

    public function create(array $commands, ContainerDefinition $definition): ContainerId
    {
        new ContainerReaper(client: $this->client)->watch(definition: $definition);

        $provisioner = new NetworkProvisioner(client: $this->client);
        $definition->networks->each(
            actions: static fn(Network $network) => $provisioner->provision(network: $network)
        );

        $executor = new StrictExecutor(client: $this->client);
        $dockerCreate = DockerCreate::from(commands: $commands, definition: $definition);

        try {
            $id = ContainerId::from(value: $executor->executeWithTimeout(command: $dockerCreate)->getOutput());
        } catch (DockerCommandExecutionFailed $failure) {
            new OwnedContainers(client: $this->client)->remove(name: $definition->name);

            throw $failure;
        }

        try {
            $definition->copyInstructions->each(
                actions: static fn(CopyInstruction $instruction) => $executor->execute(
                    command: DockerCopy::from(id: $id, instruction: $instruction)
                )
            );
        } catch (DockerCommandExecutionFailed $failure) {
            $this->client->execute(command: DockerRemove::from(id: $id));

            throw $failure;
        }

        return $id;
    }
}
