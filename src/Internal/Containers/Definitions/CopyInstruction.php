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

    public function toCopyArguments(ContainerId $id): array
    {
        return [$this->pathOnHost, sprintf('%s:%s', $id->value, $this->pathOnContainer)];
    }
}
