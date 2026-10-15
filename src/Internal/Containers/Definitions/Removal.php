<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Definitions;

enum Removal: string implements ContainerOption
{
    case MANUAL = 'manual';
    case AUTOMATIC = 'automatic';

    public function toArguments(): array
    {
        return match ($this) {
            self::MANUAL    => [],
            self::AUTOMATIC => ['--rm']
        };
    }
}
