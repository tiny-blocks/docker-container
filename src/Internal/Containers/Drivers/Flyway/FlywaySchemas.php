<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Drivers\Flyway;

use TinyBlocks\DockerContainer\Exceptions\SchemaListEmpty;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\Time\Duration;

final readonly class FlywaySchemas
{
    private const string SHELL = 'sh';
    private const string CLEAN_AND_MIGRATE_EACH = 'set -e; for schema in "$@";'
        . ' do FLYWAY_SCHEMAS="$schema" flyway clean migrate; done';

    private function __construct(private array $schemas)
    {
    }

    public static function from(array $schemas): FlywaySchemas
    {
        if (empty($schemas)) {
            throw new SchemaListEmpty();
        }

        return new FlywaySchemas(schemas: $schemas);
    }

    public function applyTo(ContainerDefinition $definition): ContainerDefinition
    {
        return $definition->withEntrypoint(executable: self::SHELL);
    }

    public function timeoutFor(Duration $timeout): Duration
    {
        return Duration::fromSeconds(seconds: ($timeout->toSeconds() * count($this->schemas)));
    }

    public function toCommands(): array
    {
        return ['-c', self::CLEAN_AND_MIGRATE_EACH, self::SHELL, ...$this->schemas];
    }
}
