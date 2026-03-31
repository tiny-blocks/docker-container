<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Contracts;

/**
 * Represents the result of a Docker command execution.
 */
interface ExecutionCompleted
{
    /**
     * Returns the output produced by the executed command.
     *
     * @return string The standard output on success, or the error output on failure.
     */
    public function getOutput(): string;

    /**
     * Indicates whether the command execution was successful.
     *
     * @return bool True if the execution was successful, false otherwise.
     */
    public function isSuccessful(): bool;
}
