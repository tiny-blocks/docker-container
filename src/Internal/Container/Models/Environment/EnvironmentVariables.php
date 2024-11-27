<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Container\Models\Environment;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Contracts\EnvironmentVariables as ContainerEnvironmentVariables;

final class EnvironmentVariables extends Collection implements ContainerEnvironmentVariables
{
    private const int LIMIT = 2;

    public static function from(array $data): EnvironmentVariables
    {
        $environmentVariables = [];

        foreach ($data as $variable) {
            [$key, $value] = explode('=', $variable, self::LIMIT);
            $environmentVariables[$key] = $value;
        }

        return self::createFrom(elements: $environmentVariables);
    }

    public function getValueBy(string $key): string
    {
        return (string)$this->toArray()[$key];
    }
}
