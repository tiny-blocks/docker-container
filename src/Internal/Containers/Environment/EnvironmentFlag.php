<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Environment;

final readonly class EnvironmentFlag
{
    private const string ENABLED = '1';

    private function __construct(private string $variable)
    {
    }

    public static function from(string $variable): EnvironmentFlag
    {
        return new EnvironmentFlag(variable: $variable);
    }

    public function isEnabled(): bool
    {
        return getenv($this->variable) === self::ENABLED;
    }
}
