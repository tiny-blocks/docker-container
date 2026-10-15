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
    private function __construct(string $reason, string $command)
    {
        $template = 'Failed to execute command <%s> in Docker container. Reason: %s';

        parent::__construct(message: sprintf($template, $command, $reason));
    }

    /**
     * Creates a DockerCommandExecutionFailed from a failure reason and the command line that produced it.
     *
     * @param string $reason The reason the command failed.
     * @param string $command The command line that failed.
     * @return DockerCommandExecutionFailed The created exception.
     */
    public static function from(string $reason, string $command): DockerCommandExecutionFailed
    {
        return new DockerCommandExecutionFailed(reason: $reason, command: $command);
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
