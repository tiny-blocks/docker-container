<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Models\Environment;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Contracts\EnvironmentVariables as ContainerEnvironmentVariables;

final class EnvironmentVariables extends Collection implements ContainerEnvironmentVariables
{
    public function getValueBy(string $key): string
    {
        return (string)$this->toArray()[$key];
    }
}
