<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

trait LineBuilder
{
    private function buildFrom(string $template, array $values): string
    {
        return trim(sprintf($template, ...$values));
    }
}
