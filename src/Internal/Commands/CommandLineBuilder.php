<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

trait CommandLineBuilder
{
    private function buildCommand(string $template, array $values): string
    {
        return trim(sprintf($template, ...$values));
    }
}
