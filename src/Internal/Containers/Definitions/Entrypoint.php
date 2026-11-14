<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Definitions;

final readonly class Entrypoint implements ContainerOption
{
    private function __construct(private string $executable)
    {
    }

    public static function from(string $executable): Entrypoint
    {
        return new Entrypoint(executable: $executable);
    }

    public function toArguments(): array
    {
        return ['--entrypoint', $this->executable];
    }
}
