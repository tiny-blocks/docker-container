<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Definitions;

final readonly class TmpfsMount implements ContainerOption
{
    private function __construct(public string $pathOnContainer)
    {
    }

    public static function from(string $pathOnContainer): TmpfsMount
    {
        return new TmpfsMount(pathOnContainer: $pathOnContainer);
    }

    public function toArguments(): array
    {
        return ['--tmpfs', $this->pathOnContainer];
    }
}
