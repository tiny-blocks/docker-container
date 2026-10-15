<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\Internal\Containers\Name;

final readonly class DockerList implements DockerCommand
{
    private function __construct(private Name $name)
    {
    }

    public static function from(Name $name): DockerList
    {
        return new DockerList(name: $name);
    }

    public function toArguments(): array
    {
        $template = 'name=^%s$';

        return ['docker', 'ps', '--all', '--quiet', '--filter', sprintf($template, $this->name->value)];
    }
}
