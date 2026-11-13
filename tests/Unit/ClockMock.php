<?php

declare(strict_types=1);

namespace Test\Unit;

final class ClockMock
{
    private const int NANOSECONDS_PER_SECOND = 1_000_000_000;
    private const int NANOSECONDS_PER_MICROSECOND = 1_000;

    private static bool $frozen = false;

    private static int $nanoseconds = 0;

    private function __construct()
    {
    }

    public static function freeze(): void
    {
        ClockMock::$frozen = true;
        ClockMock::$nanoseconds = 0;
    }

    public static function release(): void
    {
        ClockMock::$frozen = false;
    }

    public static function isFrozen(): bool
    {
        return ClockMock::$frozen;
    }

    public static function sleepFor(int $microseconds): void
    {
        ClockMock::$nanoseconds += ($microseconds * self::NANOSECONDS_PER_MICROSECOND);
    }

    public static function advanceBy(int $nanoseconds): void
    {
        ClockMock::$nanoseconds += $nanoseconds;
    }

    public static function nanoseconds(): int
    {
        return ClockMock::$nanoseconds;
    }

    public static function elapsedInSeconds(): float
    {
        return (ClockMock::$nanoseconds / self::NANOSECONDS_PER_SECOND);
    }
}
