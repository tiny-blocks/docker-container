<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Environment;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Contracts\EnvironmentVariables as ContainerEnvironmentVariables;

final readonly class EnvironmentVariables implements ContainerEnvironmentVariables
{
    private function __construct(private Collection $variables)
    {
    }

    public static function from(Collection $variables): EnvironmentVariables
    {
        return new EnvironmentVariables(variables: $variables);
    }

    public function getValueBy(string $key): string
    {
        return (string)($this->variables->toArray()[$key] ?? '');
    }
}
