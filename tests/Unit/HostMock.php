<?php

declare(strict_types=1);

namespace Test\Unit;

final class HostMock
{
    private static ?bool $insideDocker = null;

    private static ?array $shutdownCallbacks = null;

    private function __construct()
    {
    }

    public static function reset(): void
    {
        HostMock::$insideDocker = null;
        HostMock::$shutdownCallbacks = null;
    }

    public static function runShutdown(): void
    {
        foreach ((HostMock::$shutdownCallbacks ?? []) as $callback) {
            $callback();
        }
    }

    public static function insideDocker(): void
    {
        HostMock::$insideDocker = true;
    }

    public static function isOverridden(): bool
    {
        return !is_null(HostMock::$insideDocker);
    }

    public static function outsideDocker(): void
    {
        HostMock::$insideDocker = false;
    }

    public static function isInsideDocker(): bool
    {
        return HostMock::$insideDocker === true;
    }

    public static function captureShutdown(): void
    {
        HostMock::$shutdownCallbacks = [];
    }

    public static function capturesShutdown(): bool
    {
        return !is_null(HostMock::$shutdownCallbacks);
    }

    public static function registerShutdown(callable $callback): void
    {
        HostMock::$shutdownCallbacks[] = $callback;
    }

    public static function shutdownCallbackCount(): int
    {
        return count((HostMock::$shutdownCallbacks ?? []));
    }
}
