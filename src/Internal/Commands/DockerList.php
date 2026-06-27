<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\Internal\Containers\Name;

final readonly class DockerList implements Command
{
    private function __construct(public Name $name)
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
