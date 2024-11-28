<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\Internal\Commands\Options\CommandOptions;
use TinyBlocks\DockerContainer\Internal\Commands\Options\GenericCommandOption;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Name;

final readonly class DockerExecute implements Command
{
    use LineBuilder;

    private function __construct(private Name $name, private CommandOptions $commandOptions)
    {
    }

    public static function from(Name $name, array $commandOptions): DockerExecute
    {
        $commandOption = GenericCommandOption::from(commandOptions: $commandOptions);
        $commandOptions = CommandOptions::createFromOptions(commandOptions: $commandOption);

        return new DockerExecute(name: $name, commandOptions: $commandOptions);
    }

    public function toCommandLine(): string
    {
        return $this->buildFrom(
            template: 'docker exec %s %s',
            values: [$this->name->value, $this->commandOptions->toArguments()]
        );
    }
}
