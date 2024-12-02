<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Client;

use Symfony\Component\Process\Process;
use Throwable;
use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Commands\Command;
use TinyBlocks\DockerContainer\Internal\Commands\CommandWithTimeout;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;

final readonly class DockerClient implements Client
{
    public function execute(Command $command): ExecutionCompleted
    {
        $process = Process::fromShellCommandline($command->toCommandLine());

        try {
            if (is_a($command, CommandWithTimeout::class)) {
                $process->setTimeout($command->getTimeoutInWholeSeconds());
            }

            $process->run();

            return Execution::from(process: $process);
        } catch (Throwable $exception) {
            throw DockerCommandExecutionFailed::fromProcess(process: $process, exception: $exception);
        }
    }
}
