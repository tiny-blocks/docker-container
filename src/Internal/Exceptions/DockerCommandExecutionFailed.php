<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Exceptions;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class DockerCommandExecutionFailed extends RuntimeException
{
    public function __construct(Process $process, Throwable $exception)
    {
        $reason = $process->isStarted() ? $process->getErrorOutput() : $exception->getMessage();
        $template = 'Failed to execute command <%s> in Docker container. Reason: %s';

        parent::__construct(message: sprintf($template, $process->getCommandLine(), $reason));
    }
}
