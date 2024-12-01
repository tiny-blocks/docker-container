<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

use TinyBlocks\DockerContainer\Internal\Commands\LineBuilder;

final readonly class NetworkOption implements CommandOption
{
    use LineBuilder;

    private function __construct(private string $name)
    {
    }

    public static function from(string $name): NetworkOption
    {
        return new NetworkOption(name: $name);
    }

    public function toArguments(): string
    {
        return $this->buildFrom(template: '--network=%s', values: [$this->name]);
    }
}
