<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Internal\Commands\LineBuilder;

final class CommandOptions extends Collection implements CommandOption
{
    use LineBuilder;

    public static function createFromOptions(?CommandOption ...$commandOption): CommandOptions
    {
        return self::createFrom(elements: $commandOption);
    }

    public function toArguments(): string
    {
        $collection = Collection::createFromEmpty();

        $this
            ->filter()
            ->each(actions: static function (CommandOption $commandOption) use ($collection) {
                $collection->add(elements: $commandOption->toArguments());
            });

        return $this->buildFrom(template: '%s', values: [$collection->joinToString(separator: ' ')]);
    }
}
