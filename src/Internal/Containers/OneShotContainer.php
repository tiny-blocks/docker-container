<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\ExecutionCompleted;
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
        $attempt = new OneShotAttempt(name: $definition->name, client: $this->client, timeout: $timeout);
        $attempts = $definition->startAttempts();

        do {
            $attempts--;
            $id = $launcher->create(commands: $commands, definition: $definition->withoutAutoRemove());
            $execution = $attempt->run(id: $id, retryable: $attempts > 0);
        } while (is_null($execution));

        return $execution;
    }
}
