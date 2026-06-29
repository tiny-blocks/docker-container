<?php

declare(strict_types=1);

namespace Test\Unit;

use TinyBlocks\DockerContainer\Internal\Containers\ShutdownHook;

final class ShutdownHookMock implements ShutdownHook
{
    private int $registrations = 0;

    public function register(callable $callback): void
    {
        $this->registrations++;
    }

    public function getRegistrationCount(): int
    {
        return $this->registrations;
    }
}
