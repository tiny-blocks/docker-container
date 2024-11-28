<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Models;

use InvalidArgumentException;

final readonly class Image
{
    private function __construct(public string $name)
    {
        if (empty($name)) {
            throw new InvalidArgumentException(message: 'Image name cannot be empty.');
        }
    }

    public static function from(string $image): Image
    {
        return new Image(name: $image);
    }
}
