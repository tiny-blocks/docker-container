<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

final readonly class Port implements CommandOption
{
    private function __construct(private int $portOnHost, private int $portOnContainer)
    {
    }

    public static function from(int $portOnHost, int $portOnContainer): Port
    {
        return new Port(portOnHost: $portOnHost, portOnContainer: $portOnContainer);
    }

    public function toArguments(): string
    {
        return sprintf('--publish %d:%d', $this->portOnHost, $this->portOnContainer);
    }
}
