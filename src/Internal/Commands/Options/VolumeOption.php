<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

use TinyBlocks\DockerContainer\Internal\Commands\LineBuilder;

final readonly class VolumeOption implements CommandOption
{
    use LineBuilder;

    private function __construct(public string $pathOnHost, public string $pathOnContainer)
    {
    }

    public static function from(string $pathOnHost, string $pathOnContainer): VolumeOption
    {
        return new VolumeOption(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer);
    }

    public function toArguments(): string
    {
        return $this->buildFrom(template: '--volume %s:%s', values: [$this->pathOnHost, $this->pathOnContainer]);
    }
}
