<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

use TinyBlocks\DockerContainer\Internal\Commands\LineBuilder;

enum SimpleCommandOption: string implements CommandOption
{
    use LineBuilder;

    case ALL = 'all';
    case QUIET = 'quiet';
    case REMOVE = 'rm';
    case DETACH = 'detach';
    case FILTER = 'filter';

    public function toArguments(): string
    {
        return $this->buildFrom(template: '--%s', values: [$this->value]);
    }
}
