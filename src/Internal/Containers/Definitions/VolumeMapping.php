<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Definitions;

final readonly class VolumeMapping
{
    private function __construct(public string $pathOnHost, public string $pathOnContainer)
    {
    }

    public static function from(string $pathOnHost, string $pathOnContainer): VolumeMapping
    {
        return new VolumeMapping(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer);
    }

    public function toArgument(): string
    {
        return sprintf('--volume %s:%s', $this->pathOnHost, $this->pathOnContainer);
    }
}
