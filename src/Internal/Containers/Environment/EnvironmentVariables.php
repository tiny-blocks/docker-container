<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Environment;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Contracts\EnvironmentVariables as ContainerEnvironmentVariables;

final readonly class EnvironmentVariables implements ContainerEnvironmentVariables
{
    private function __construct(private array $variables)
    {
    }

    public static function from(Collection $variables): EnvironmentVariables
    {
        return new EnvironmentVariables(variables: $variables->toArray());
    }

    public function getValueBy(string $key): string
    {
        return (string)($this->variables[$key] ?? '');
    }
}
