<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Exceptions;

use RuntimeException;
use TinyBlocks\Time\Duration;

/**
 * Raised when a container does not reach the awaited state (ready or exited) within its timeout.
 */
final class ContainerWaitTimeout extends RuntimeException
{
    /**
     * Creates a ContainerWaitTimeout for a container that did not exit within the timeout.
     *
     * @param string $name The name of the container that kept running.
     * @param Duration $timeout The timeout that elapsed.
     * @return ContainerWaitTimeout The created exception.
     */
    public static function forExit(string $name, Duration $timeout): ContainerWaitTimeout
    {
        $template = 'Container <%s> did not exit within <%d> seconds.';

        return new ContainerWaitTimeout(message: sprintf($template, $name, $timeout->toSeconds()));
    }

    /**
     * Creates a ContainerWaitTimeout for a readiness condition that did not hold within the timeout.
     *
     * @param Duration $timeout The timeout that elapsed.
     * @return ContainerWaitTimeout The created exception.
     */
    public static function forReadiness(Duration $timeout): ContainerWaitTimeout
    {
        $template = 'Container readiness check timed out after <%d> seconds.';

        return new ContainerWaitTimeout(message: sprintf($template, $timeout->toSeconds()));
    }
}
