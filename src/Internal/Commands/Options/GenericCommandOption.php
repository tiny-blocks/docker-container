<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Internal\Commands\LineBuilder;

final readonly class GenericCommandOption implements CommandOption
{
    use LineBuilder;

    private function __construct(private Collection $commandOptions)
    {
    }

    public static function from(array $commandOptions): GenericCommandOption
    {
        $commandOptions = Collection::createFrom(elements: $commandOptions);

        return new GenericCommandOption(commandOptions: $commandOptions);
    }

    public function toArguments(): string
    {
        return $this->buildFrom(template: '%s', values: [$this->commandOptions->joinToString(separator: ' ')]);
    }
}
