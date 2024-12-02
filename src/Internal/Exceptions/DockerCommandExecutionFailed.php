<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Exceptions;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Commands\Command;

final class DockerCommandExecutionFailed extends RuntimeException
{
    public function __construct(string $reason, string $command)
    {
        $template = 'Failed to execute command <%s> in Docker container. Reason: %s';

        parent::__construct(message: sprintf($template, $command, $reason));
    }

    public static function fromProcess(Process $process, Throwable $exception): DockerCommandExecutionFailed
    {
        $reason = $process->isStarted() ? $process->getErrorOutput() : $exception->getMessage();

        return new DockerCommandExecutionFailed(reason: $reason, command: $process->getCommandLine());
    }

    public static function fromCommand(Command $command, ExecutionCompleted $execution): DockerCommandExecutionFailed
    {
        return new DockerCommandExecutionFailed(reason: $execution->getOutput(), command: $command->toCommandLine());
    }
}
