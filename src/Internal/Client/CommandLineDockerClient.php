<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Client;

use Symfony\Component\Process\Process;
use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\DockerCommandWithTimeout;
use TinyBlocks\DockerContainer\ExecutionCompleted;

final readonly class CommandLineDockerClient implements DockerClient
{
    public function execute(DockerCommand $command): ExecutionCompleted
    {
        return new CommandLine(process: new Process(command: $command->toArguments()))->run();
    }

    public function executeWithTimeout(DockerCommandWithTimeout $command): ExecutionCompleted
    {
        $process = new Process(command: $command->toArguments(), timeout: $command->timeout()->toSeconds());

        return new CommandLine(process: $process)->run();
    }
}
