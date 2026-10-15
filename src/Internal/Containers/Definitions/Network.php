<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Definitions;

final readonly class Network implements ContainerOption
{
    private function __construct(public string $name)
    {
    }

    public static function from(string $name): Network
    {
        return new Network(name: $name);
    }

    public function toArguments(): array
    {
        $template = '--network=%s';

        return [sprintf($template, $this->name)];
    }
}
