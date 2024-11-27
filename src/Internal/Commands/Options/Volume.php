<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

final readonly class Volume implements CommandOption
{
    private function __construct(public string $pathOnHost, public string $pathOnContainer)
    {
    }

    public static function from(string $pathOnHost, string $pathOnContainer): Volume
    {
        return new Volume(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer);
    }

    public function toArguments(): string
    {
        return sprintf('--volume %s:%s', $this->pathOnHost, $this->pathOnContainer);
    }
}
