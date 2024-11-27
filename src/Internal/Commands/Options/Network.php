<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

use TinyBlocks\DockerContainer\NetworkDrivers;

final readonly class Network implements CommandOption
{
    private function __construct(private NetworkDrivers $driver)
    {
    }

    public static function from(NetworkDrivers $driver): Network
    {
        return new Network(driver: $driver);
    }

    public function toArguments(): string
    {
        return sprintf('--network %s', $this->driver->value);
    }
}
