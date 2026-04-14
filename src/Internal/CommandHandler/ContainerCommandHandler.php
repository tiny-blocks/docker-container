<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\CommandHandler;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\Commands\Command;
use TinyBlocks\DockerContainer\Internal\Commands\DockerCopy;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerNetworkCreate;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Containers\ContainerLookup;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\CopyInstruction;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;
use TinyBlocks\DockerContainer\Internal\Containers\ShutdownHook;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

final readonly class ContainerCommandHandler implements CommandHandler
{
    private ContainerLookup $lookup;

    public function __construct(private Client $client, ShutdownHook $shutdownHook)
    {
        $this->lookup = new ContainerLookup(client: $client, shutdownHook: $shutdownHook);
    }

    public function execute(Command $command): ExecutionCompleted
    {
        return $this->client->execute(command: $command);
    }

    public function findBy(ContainerDefinition $definition): ?ContainerStarted
    {
        $dockerList = DockerList::from(name: $definition->name);
        $executionCompleted = $this->client->execute(command: $dockerList);

        $output = trim($executionCompleted->getOutput());

        if (empty($output)) {
            return null;
        }

        $id = ContainerId::from(value: $output);

        return $this->lookup->byId(id: $id, definition: $definition, commandHandler: $this);
    }

    public function run(DockerRun $dockerRun): ContainerStarted
    {
        if (!is_null($dockerRun->definition->network)) {
            $this->client->execute(command: DockerNetworkCreate::from(network: $dockerRun->definition->network));
        }

        $executionCompleted = $this->client->execute(command: $dockerRun);

        if (!$executionCompleted->isSuccessful()) {
            throw DockerCommandExecutionFailed::fromCommand(command: $dockerRun, execution: $executionCompleted);
        }

        $id = ContainerId::from(value: $executionCompleted->getOutput());

        $started = $this->lookup->byId(id: $id, definition: $dockerRun->definition, commandHandler: $this);

        $dockerRun->definition->copyInstructions->each(
            actions: function (CopyInstruction $instruction) use ($id): void {
                $this->client->execute(command: DockerCopy::from(id: $id, instruction: $instruction));
            }
        );

        return $started;
    }
}
