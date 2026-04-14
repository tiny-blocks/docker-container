<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

$registeredShutdownCallbacks = [];

function register_shutdown_function(callable $callback): void
{
    global $registeredShutdownCallbacks;
    $registeredShutdownCallbacks[] = $callback;
}

