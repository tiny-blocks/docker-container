<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits\Conditions;

/**
 * Defines a readiness condition used to determine if a container dependency is available.
 */
interface ContainerReady
{
    /**
     * Checks whether the container dependency is ready to accept connections.
     *
     * @return bool True if the dependency is ready, false otherwise.
     */
    public function isReady(): bool;
}
