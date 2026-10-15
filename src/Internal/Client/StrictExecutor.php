<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Client;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\ExecutionCompleted;

final readonly class StrictExecutor
{
    public function __construct(private DockerClient $client)
    {
    }

    public function execute(DockerCommand $command): ExecutionCompleted
    {
        $executionCompleted = $this->client->execute(command: $command);

        if (!$executionCompleted->isSuccessful()) {
            throw DockerCommandExecutionFailed::fromCommand(command: $command, execution: $executionCompleted);
        }

        return $executionCompleted;
    }
}
