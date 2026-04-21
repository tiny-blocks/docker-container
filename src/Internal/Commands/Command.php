<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

/**
 * Represents a Docker CLI command that exposes its tokenized argument list for
 * direct use with Symfony Process's array form.
 */
interface Command
{
    /**
     * Converts the command to its argument-list representation.
     *
     * The first element is the executable, remaining elements are its arguments. No shell
     * interpretation happens between elements, so values are passed through verbatim.
     *
     * @return array<int, string> Ordered argument list.
     */
    public function toArguments(): array;
}
