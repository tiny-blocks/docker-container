<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\CommandHandler;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\Commands\Command;
use TinyBlocks\DockerContainer\Internal\Commands\DockerCopy;
use TinyBlocks\DockerContainer\Internal\Commands\DockerInspect;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\CopyInstruction;
use TinyBlocks\DockerContainer\Internal\Containers\Factories\InspectResultParser;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;
use TinyBlocks\DockerContainer\Internal\Containers\Started;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerContainerNotFound;

final readonly class ContainerCommandHandler implements CommandHandler
{
    private InspectResultParser $parser;

    public function __construct(private Client $client)
    {
        $this->parser = new InspectResultParser();
    }

    public function run(DockerRun $dockerRun): ContainerStarted
    {
        $executionCompleted = $this->client->execute(command: $dockerRun);

        if (!$executionCompleted->isSuccessful()) {
            throw DockerCommandExecutionFailed::fromCommand(command: $dockerRun, execution: $executionCompleted);
        }

        $id = ContainerId::from(value: $executionCompleted->getOutput());
        $definition = $dockerRun->definition;

        $started = $this->inspect(id: $id, definition: $definition);

        $definition->copyInstructions->each(
            actions: function (CopyInstruction $instruction) use ($id): void {
                $this->client->execute(command: DockerCopy::from(instruction: $instruction, id: $id));
            }
        );

        return $started;
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

        return $this->inspect(id: $id, definition: $definition);
    }

    public function execute(Command $command): ExecutionCompleted
    {
        return $this->client->execute(command: $command);
    }

    private function inspect(ContainerId $id, ContainerDefinition $definition): ContainerStarted
    {
        $dockerInspect = DockerInspect::from(id: $id);
        $executionCompleted = $this->client->execute(command: $dockerInspect);

        $payload = (array)json_decode($executionCompleted->getOutput(), true);

        if (empty(array_filter($payload))) {
            throw new DockerContainerNotFound(name: $definition->name);
        }

        $data = $payload[0];

        return new Started(
            id: $id,
            name: $definition->name,
            address: $this->parser->parseAddress(data: $data),
            environmentVariables: $this->parser->parseEnvironmentVariables(data: $data),
            commandHandler: $this
        );
    }
}
