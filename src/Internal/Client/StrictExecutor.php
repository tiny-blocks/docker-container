<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Client;

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\DockerCommandWithTimeout;
use TinyBlocks\DockerContainer\ExecutionCompleted;

final readonly class StrictExecutor
{
    public function __construct(private DockerClient $client)
    {
    }

    public function execute(DockerCommand $command): ExecutionCompleted
    {
        return CommandExecution::from(command: $command, execution: $this->client->execute(command: $command))
            ->successful();
    }

    public function executeWithTimeout(DockerCommandWithTimeout $command): ExecutionCompleted
    {
        $execution = $this->client->executeWithTimeout(command: $command);

        return CommandExecution::from(command: $command, execution: $execution)->successful();
    }
}
