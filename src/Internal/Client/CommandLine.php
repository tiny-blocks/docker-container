<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Client;

use Symfony\Component\Process\Process;
use Throwable;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;

final readonly class CommandLine
{
    public function __construct(private Process $process)
    {
    }

    public function run(): Execution
    {
        try {
            $this->process->run();
        } catch (Throwable $exception) {
            throw new DockerCommandExecutionFailed(
                reason: $exception->getMessage(),
                command: $this->process->getCommandLine()
            );
        }

        return Execution::from(process: $this->process);
    }
}
