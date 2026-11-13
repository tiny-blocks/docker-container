<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\Internal\Client\StrictExecutor;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRemove;
use TinyBlocks\DockerContainer\Internal\Commands\DockerStart;

final readonly class ContainerStartAttempt
{
    public function __construct(private DockerClient $client)
    {
    }

    public function failureOf(ContainerId $id): ?DockerCommandExecutionFailed
    {
        try {
            new StrictExecutor(client: $this->client)->execute(command: DockerStart::from(id: $id));

            return null;
        } catch (DockerCommandExecutionFailed $failure) {
            $this->client->execute(command: DockerRemove::from(id: $id));

            return $failure;
        }
    }
}
