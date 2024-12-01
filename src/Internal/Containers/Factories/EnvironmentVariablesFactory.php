<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Factories;

use TinyBlocks\DockerContainer\Internal\Containers\Models\Environment\EnvironmentVariables;

final readonly class EnvironmentVariablesFactory
{
    private const int LIMIT = 2;
    private const string SEPARATOR = '=';

    public function buildFrom(array $data): EnvironmentVariables
    {
        $data = $data['Config']['Env'];
        $environmentVariables = [];

        foreach ($data as $variable) {
            [$key, $value] = explode(self::SEPARATOR, $variable, self::LIMIT);
            $environmentVariables[$key] = $value;
        }

        return EnvironmentVariables::createFrom(elements: $environmentVariables);
    }
}
