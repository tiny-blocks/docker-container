<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\Internal\Commands\Options\CommandOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\CommandOptions;

final readonly class DockerCopy implements Command
{
    use CommandLineBuilder;

    private function __construct(private CommandOptions $commandOptions)
    {
    }

    public static function from(?CommandOption ...$commandOption): DockerCopy
    {
        $commandOptions = CommandOptions::createFromOptions(...$commandOption);

        return new DockerCopy(commandOptions: $commandOptions);
    }

    public function toCommandLine(): string
    {
        return $this->buildCommand(template: 'docker cp %s', values: [$this->commandOptions->toArguments()]);
    }
}
