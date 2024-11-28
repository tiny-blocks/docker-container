<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

use TinyBlocks\DockerContainer\Internal\Commands\LineBuilder;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;

final readonly class ItemToCopyOption implements CommandOption
{
    use LineBuilder;

    private function __construct(private ContainerId $id, private VolumeOption $volume)
    {
    }

    public static function from(ContainerId $id, VolumeOption $volume): ItemToCopyOption
    {
        return new ItemToCopyOption(id: $id, volume: $volume);
    }

    public function toArguments(): string
    {
        return $this->buildFrom(
            template: '%s %s:%s',
            values: [$this->volume->pathOnHost, $this->id->value, $this->volume->pathOnContainer]
        );
    }
}
