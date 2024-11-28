<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\Internal\Commands\Options\CommandOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\CommandOptions;
use TinyBlocks\DockerContainer\Internal\Commands\Options\SimpleCommandOption;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Container;

final readonly class DockerList implements Command
{
    use LineBuilder;

    private function __construct(public Container $container, public CommandOptions $commandOptions)
    {
    }

    public static function from(Container $container, ?CommandOption ...$commandOption): DockerList
    {
        $commandOptions = CommandOptions::createFromOptions(
            SimpleCommandOption::ALL,
            SimpleCommandOption::QUIET,
            ...$commandOption
        );

        return new DockerList(container: $container, commandOptions: $commandOptions);
    }

    public function toCommandLine(): string
    {
        return $this->buildFrom(
            template: 'docker ps %s %s name=%s',
            values: [
                $this->commandOptions->toArguments(),
                SimpleCommandOption::FILTER->toArguments(),
                $this->container->name->value
            ]
        );
    }
}
