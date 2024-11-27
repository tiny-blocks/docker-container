<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

enum SimpleCommandOption: string implements CommandOption
{
    case ALL = 'all';
    case QUIET = 'quiet';
    case REMOVE = 'rm';
    case DETACH = 'detach';
    case FILTER = 'filter';

    public function toArguments(): string
    {
        return sprintf('--%s', $this->value);
    }
}
