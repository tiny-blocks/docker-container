<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

use TinyBlocks\DockerContainer\Internal\Commands\LineBuilder;

final readonly class PortOption implements CommandOption
{
    use LineBuilder;

    private function __construct(private int $portOnHost, private int $portOnContainer)
    {
    }

    public static function from(int $portOnHost, int $portOnContainer): PortOption
    {
        return new PortOption(portOnHost: $portOnHost, portOnContainer: $portOnContainer);
    }

    public function toArguments(): string
    {
        return $this->buildFrom(template: '--publish %d:%d', values: [$this->portOnHost, $this->portOnContainer]);
    }
}
