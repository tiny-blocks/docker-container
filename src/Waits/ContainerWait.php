<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

/**
 * Defines the strategy for waiting conditions that ensure a Docker container meets a specific requirement
 * before proceeding with further actions.
 */
interface ContainerWait
{
    /**
     * The default wait time in whole seconds.
     *
     * This constant represents the default amount of time the system will wait when no specific condition is set.
     * It can be overridden or used directly in waiting mechanisms.
     */
    public const int WAIT_TIME_IN_WHOLE_SECONDS = 1;
}
