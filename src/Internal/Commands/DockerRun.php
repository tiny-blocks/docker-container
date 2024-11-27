<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Internal\Commands\Options\CommandOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\CommandOptions;
use TinyBlocks\DockerContainer\Internal\Container\Models\Container;

final readonly class DockerRun implements Command
{
    use CommandLineBuilder;

    private function __construct(
        public Collection $commands,
        public Container $container,
        public CommandOptions $commandOptions
    ) {
    }

    public static function from(array $commands, Container $container, ?CommandOption ...$commandOption): DockerRun
    {
        $commands = Collection::createFrom(elements: $commands);
        $commandOptions = CommandOptions::createFromOptions(...$commandOption);

        return new DockerRun(commands: $commands, container: $container, commandOptions: $commandOptions);
    }

    public function toCommandLine(): string
    {
        $name = $this->container->name->value;

        return $this->buildCommand(
            template: 'docker run --user root --name %s --hostname %s %s %s %s',
            values: [
                $name,
                $name,
                $this->commandOptions->toArguments(),
                $this->container->image->name,
                $this->commands->joinToString(separator: ' ')
            ]
        );
    }
}
