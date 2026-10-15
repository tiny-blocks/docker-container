<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\Exceptions\ContainerExitUnsuccessful;
use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRemove;
use TinyBlocks\DockerContainer\Internal\Commands\DockerStartAttached;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\Time\Duration;

final readonly class OneShotContainer
{
    public function __construct(private DockerClient $client)
    {
    }

    public function run(Duration $timeout, array $commands, ContainerDefinition $definition): ExecutionCompleted
    {
        $launcher = new ContainerLauncher(client: $this->client);
        $id = $launcher->create(commands: $commands, definition: $definition->withoutAutoRemove());
        $startAttached = DockerStartAttached::from(id: $id, timeout: $timeout);

        try {
            $execution = $this->client->executeWithTimeout(command: $startAttached);

            if (!$execution->isSuccessful()) {
                throw new ContainerExitUnsuccessful(name: $definition->name->value, output: $execution->getOutput());
            }

            return $execution;
        } catch (DockerCommandExecutionFailed) {
            throw ContainerWaitTimeout::forExit(name: $definition->name->value, timeout: $timeout);
        } finally {
            $this->client->execute(command: DockerRemove::from(id: $id));
        }
    }
}
