<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use Closure;

trait WaitForCondition
{
    private const int SECONDS = 1;

    public function waitFor(Closure $condition): void
    {
        while (!$condition()) {
            sleep(self::SECONDS);
        }
    }
}
