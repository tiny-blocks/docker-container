<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

use TinyBlocks\Collection\Collection;

final readonly class GenericCommandOption implements CommandOption
{
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
        return $this->commandOptions->joinToString(separator: ' ');
    }
}
