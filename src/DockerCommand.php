<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

/**
 * A Docker command line the library issues through a {@see DockerClient}.
 *
 * <p>A command bounded by a timeout of its own is a {@see DockerCommandWithTimeout}. Any other command runs within
 * the default timeout of the client.</p>
 */
interface DockerCommand
{
    /**
     * Returns the DockerCommand as an ordered argument list.
     *
     * <p>The first element is the executable and the remaining elements are its arguments. No shell interpretation
     * happens between elements, so values are passed through verbatim.</p>
     *
     * @return array<int, string> The ordered argument list.
     */
    public function toArguments(): array;
}
