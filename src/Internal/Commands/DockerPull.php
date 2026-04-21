<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

final readonly class DockerPull implements Command
{
    private function __construct(private string $image)
    {
    }

    public static function from(string $image): DockerPull
    {
        return new DockerPull(image: $image);
    }

    public function toArguments(): array
    {
        return ['docker', 'pull', $this->image];
    }
}
