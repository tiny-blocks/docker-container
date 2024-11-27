<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

use TinyBlocks\DockerContainer\Internal\Container\Models\ContainerId;

final readonly class Item implements CommandOption
{
    private function __construct(private ContainerId $id, private Volume $volume)
    {
    }

    public static function from(ContainerId $id, Volume $volume): Item
    {
        return new Item(id: $id, volume: $volume);
    }

    public function toArguments(): string
    {
        return sprintf('%s %s:%s', $this->volume->pathOnHost, $this->id->value, $this->volume->pathOnContainer);
    }
}
