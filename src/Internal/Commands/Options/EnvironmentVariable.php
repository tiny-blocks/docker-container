<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

final readonly class EnvironmentVariable implements CommandOption
{
    private function __construct(private string $key, private string $value)
    {
    }

    public static function from(string $key, string $value): EnvironmentVariable
    {
        return new EnvironmentVariable(key: $key, value: $value);
    }

    public function toArguments(): string
    {
        return sprintf('--env %s=%s', $this->key, escapeshellarg($this->value));
    }
}
