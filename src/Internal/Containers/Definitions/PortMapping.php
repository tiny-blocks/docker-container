<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Definitions;

final readonly class PortMapping
{
    private function __construct(public int $portOnHost, public int $portOnContainer)
    {
    }

    public static function from(int $portOnHost, int $portOnContainer): PortMapping
    {
        return new PortMapping(portOnHost: $portOnHost, portOnContainer: $portOnContainer);
    }

    public function toArgument(): string
    {
        return sprintf('--publish %d:%d', $this->portOnHost, $this->portOnContainer);
    }
}
