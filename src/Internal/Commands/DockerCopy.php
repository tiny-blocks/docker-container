<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\Internal\Containers\Definitions\CopyInstruction;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;

final readonly class DockerCopy implements Command
{
    private function __construct(private ContainerId $id, private CopyInstruction $instruction)
    {
    }

    public static function from(ContainerId $id, CopyInstruction $instruction): DockerCopy
    {
        return new DockerCopy(id: $id, instruction: $instruction);
    }

    public function toCommandLine(): string
    {
        return sprintf('docker cp %s', $this->instruction->toCopyArgument(id: $this->id));
    }
}
