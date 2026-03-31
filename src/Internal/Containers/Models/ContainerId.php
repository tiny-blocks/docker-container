<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Models;

use InvalidArgumentException;

final readonly class ContainerId
{
    private const int CONTAINER_ID_OFFSET = 0;
    private const int CONTAINER_ID_LENGTH = 12;

    private function __construct(public string $value)
    {
    }

    public static function from(string $value): ContainerId
    {
        $trimmed = trim($value);

        if (empty($trimmed)) {
            throw new InvalidArgumentException(message: 'Container ID cannot be empty.');
        }

        if (strlen($trimmed) < self::CONTAINER_ID_LENGTH) {
            $template = 'Container ID <%s> is too short. Minimum length is <%d> characters.';
            throw new InvalidArgumentException(
                message: sprintf($template, $trimmed, self::CONTAINER_ID_LENGTH)
            );
        }

        return new ContainerId(value: substr($trimmed, self::CONTAINER_ID_OFFSET, self::CONTAINER_ID_LENGTH));
    }
}
