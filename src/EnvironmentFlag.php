<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use Closure;

/**
 * Builds reusable predicates from environment variables.
 */
final class EnvironmentFlag
{
    private function __construct()
    {
    }

    /**
     * Builds a predicate that tells whether the environment variable is set to "1".
     *
     * @param string $name The environment variable name.
     * @return Closure(): bool A predicate that reads the variable when evaluated.
     */
    public static function enabled(string $name): Closure
    {
        return static fn(): bool => getenv($name) === '1';
    }
}
