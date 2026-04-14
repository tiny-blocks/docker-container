<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

final readonly class ShutdownHook
{
    public function register(callable $callback): void
    {
        register_shutdown_function($callback);
    }
}
