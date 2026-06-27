<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\Internal\Exceptions\ImageNameEmpty;

final readonly class Image
{
    private function __construct(public string $name)
    {
        if ($name === '') {
            throw new ImageNameEmpty();
        }
    }

    public static function from(string $image): Image
    {
        return new Image(name: $image);
    }
}
