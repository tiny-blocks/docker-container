<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Definitions;

final readonly class EnvironmentVariable
{
    private function __construct(public string $key, public string $value)
    {
    }

    public static function from(string $key, string $value): EnvironmentVariable
    {
        return new EnvironmentVariable(key: $key, value: $value);
    }

    public function toArgument(): string
    {
        return sprintf('--env %s=%s', $this->key, escapeshellarg($this->value));
    }
}
