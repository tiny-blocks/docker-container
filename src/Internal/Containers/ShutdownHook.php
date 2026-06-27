<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

/**
 * Registers a callback to run when the PHP process shuts down.
 */
interface ShutdownHook
{
    /**
     * Registers the callback to run on process shutdown.
     *
     * @param callable $callback The callback invoked during shutdown.
     */
    public function register(callable $callback): void;
}
