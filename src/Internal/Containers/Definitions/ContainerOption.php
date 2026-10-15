<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Definitions;

/**
 * A part of a container definition that contributes its own options to the <code>docker create</code> command.
 */
interface ContainerOption
{
    /**
     * Returns the ContainerOption as the ordered arguments it adds to <code>docker create</code>.
     *
     * @return array<int, string> The ordered arguments.
     */
    public function toArguments(): array;
}
