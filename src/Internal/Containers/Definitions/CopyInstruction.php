<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Definitions;

use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;

final readonly class CopyInstruction
{
    private function __construct(public string $pathOnHost, public string $pathOnContainer)
    {
    }

    public static function from(string $pathOnHost, string $pathOnContainer): CopyInstruction
    {
        return new CopyInstruction(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer);
    }

    public function toCopyArgument(ContainerId $id): string
    {
        return sprintf('%s %s:%s', $this->pathOnHost, $id->value, $this->pathOnContainer);
    }
}
