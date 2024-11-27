<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands\Options;

use TinyBlocks\Collection\Collection;

final class CommandOptions extends Collection implements CommandOption
{
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

        return $collection->joinToString(separator: ' ');
    }
}
