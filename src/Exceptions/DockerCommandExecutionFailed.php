<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Exceptions;

use RuntimeException;
use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\ExecutionCompleted;

/**
 * Raised when a Docker command cannot run, exceeds its timeout, or reports a failure the library cannot recover from.
 */
final class DockerCommandExecutionFailed extends RuntimeException
{
    public function __construct(string $reason, string $command)
    {
        $template = 'Failed to execute command <%s> in Docker container. Reason: %s';

        parent::__construct(message: sprintf($template, $command, $reason));
    }

    /**
     * Creates a DockerCommandExecutionFailed from a command and the unsuccessful outcome it produced.
     *
     * @param DockerCommand $command The command that failed.
     * @param ExecutionCompleted $execution The unsuccessful outcome of the command.
     * @return DockerCommandExecutionFailed The created exception, with the command rendered shell-escaped.
     */
    public static function fromCommand(
        DockerCommand $command,
        ExecutionCompleted $execution
    ): DockerCommandExecutionFailed {
        $rendered = implode(' ', array_map('escapeshellarg', $command->toArguments()));

        return new DockerCommandExecutionFailed(reason: $execution->getOutput(), command: $rendered);
    }
}
