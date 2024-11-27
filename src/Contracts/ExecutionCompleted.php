<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Contracts;

/**
 * Represents the result of a completed command execution.
 */
interface ExecutionCompleted
{
    /**
     * Returns the output of the executed command.
     *
     * @return string The command output.
     */
    public function getOutput(): string;

    /**
     * Returns whether the command execution was successful.
     *
     * @return bool True if the command was successful, false otherwise.
     */
    public function isSuccessful(): bool;
}
