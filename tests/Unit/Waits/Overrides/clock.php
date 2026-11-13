<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use Test\Unit\ClockMock;

use function hrtime as monotonicTime;
use function usleep as pause;

function hrtime(bool $asNumber): float|int
{
    if ($asNumber && ClockMock::isFrozen()) {
        return ClockMock::nanoseconds();
    }

    return monotonicTime(true);
}

function usleep(int $microseconds): void
{
    if (ClockMock::isFrozen()) {
        ClockMock::sleepFor(microseconds: $microseconds);

        return;
    }

    pause($microseconds);
}
