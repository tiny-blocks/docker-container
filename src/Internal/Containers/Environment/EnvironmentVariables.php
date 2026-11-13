<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Environment;

use TinyBlocks\DockerContainer\EnvironmentVariables as ContainerEnvironmentVariables;

final readonly class EnvironmentVariables implements ContainerEnvironmentVariables
{
    private function __construct(private array $variables)
    {
    }

    public static function from(array $variables): EnvironmentVariables
    {
        return new EnvironmentVariables(variables: $variables);
    }

    public function getValueBy(string $key): string
    {
        return (string)($this->variables[$key] ?? '');
    }
}
