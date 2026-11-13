<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use Test\Unit\HostMock;

use function file_exists as fileExists;
use function register_shutdown_function as registerShutdownFunction;

function file_exists(string $filename): bool
{
    if (HostMock::isOverridden()) {
        return HostMock::isInsideDocker();
    }

    return fileExists($filename);
}

function register_shutdown_function(callable $callback): void
{
    if (HostMock::capturesShutdown()) {
        HostMock::registerShutdown(callback: $callback);

        return;
    }

    registerShutdownFunction($callback);
}
