<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

final readonly class HostEnvironment
{
    public static function isInsideDocker(): bool
    {
        return file_exists('/.dockerenv');
    }

    public static function containerHostname(): string
    {
        return (string) gethostname();
    }
}
