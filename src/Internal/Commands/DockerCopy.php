<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\Internal\Containers\Definitions\CopyInstruction;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;

final readonly class DockerCopy implements Command
{
    private function __construct(private CopyInstruction $instruction, private ContainerId $id)
    {
    }

    public static function from(CopyInstruction $instruction, ContainerId $id): DockerCopy
    {
        return new DockerCopy(instruction: $instruction, id: $id);
    }

    public function toCommandLine(): string
    {
        return sprintf('docker cp %s', $this->instruction->toCopyArgument(id: $this->id));
    }
}
