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
use TinyBlocks\Time\Duration;

final readonly class OneShotAttempt
{
    public function __construct(private Name $name, private DockerClient $client, private Duration $timeout)
    {
    }

    public function run(ContainerId $id, bool $retryable): ?ExecutionCompleted
    {
        try {
            $execution = $this->client->executeWithTimeout(
                command: DockerStartAttached::from(id: $id, timeout: $this->timeout)
            );

            if ($execution->isSuccessful()) {
                return $execution;
            }

            if ($retryable && new ContainerLookup(client: $this->client)->wasNeverStarted(id: $id)) {
                return null;
            }

            throw new ContainerExitUnsuccessful(name: $this->name->value, output: $execution->getOutput());
        } catch (DockerCommandExecutionFailed) {
            throw ContainerWaitTimeout::forExit(name: $this->name->value, timeout: $this->timeout);
        } finally {
            $this->client->execute(command: DockerRemove::from(id: $id));
        }
    }
}
