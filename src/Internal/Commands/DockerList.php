<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\Internal\Containers\Name;
use TinyBlocks\DockerContainer\Internal\Containers\Run;

final readonly class DockerList implements DockerCommand
{
    private function __construct(private Run $run, private Name $name)
    {
    }

    public static function from(Run $run, Name $name): DockerList
    {
        return new DockerList(run: $run, name: $name);
    }

    public function toArguments(): array
    {
        $template = 'name=^%s$';

        return [
            'docker',
            'ps',
            '--all',
            '--quiet',
            '--filter',
            sprintf($template, $this->name->value),
            '--filter',
            $this->run->filter()
        ];
    }
}
