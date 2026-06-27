<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

function register_shutdown_function(callable $callback): void
{
    global $registeredShutdownCallbacks;

    if (!is_array($registeredShutdownCallbacks)) {
        $registeredShutdownCallbacks = [];
    }

    $registeredShutdownCallbacks[] = $callback;
}
