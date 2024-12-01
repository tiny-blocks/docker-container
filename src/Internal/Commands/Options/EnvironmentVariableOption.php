<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

use TinyBlocks\DockerContainer\Internal\Commands\LineBuilder;

final readonly class EnvironmentVariableOption implements CommandOption
{
    use LineBuilder;

    private function __construct(private string $key, private string $value)
    {
    }

    public static function from(string $key, string $value): EnvironmentVariableOption
    {
        return new EnvironmentVariableOption(key: $key, value: $value);
    }

    public function toArguments(): string
    {
        return $this->buildFrom(template: '--env %s=%s', values: [$this->key, escapeshellarg($this->value)]);
    }
}
