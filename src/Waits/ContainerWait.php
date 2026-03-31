<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

/**
 * Defines constants for waiting for a container to be ready. These constants can be used by any
 * implementation of a container wait strategy, such as polling or event-based waits.
 */
interface ContainerWait
{
    /** Default timeout for waiting, in seconds. */
    public const int DEFAULT_TIMEOUT_IN_SECONDS = 30;

    /** Default interval between polls, in microseconds. */
    public const int DEFAULT_POLL_INTERVAL_IN_MICROSECONDS = 250_000;
}
