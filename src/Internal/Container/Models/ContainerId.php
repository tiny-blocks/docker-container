<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Container\Models;

use InvalidArgumentException;

final readonly class ContainerId
{
    private const int CONTAINER_ID_OFFSET = 0;
    private const int CONTAINER_ID_LENGTH = 12;

    private function __construct(public string $value)
    {
    }

    public static function from(string $value): self
    {
        if (empty($value)) {
            throw new InvalidArgumentException(message: 'Container ID cannot be empty.');
        }

        if (strlen($value) < self::CONTAINER_ID_LENGTH) {
            $template = 'Container ID <%s> is too short. Minimum length is <%d> characters.';
            throw new InvalidArgumentException(message: sprintf($template, $value, self::CONTAINER_ID_LENGTH));
        }

        return new ContainerId(value: substr($value, self::CONTAINER_ID_OFFSET, self::CONTAINER_ID_LENGTH));
    }
}
