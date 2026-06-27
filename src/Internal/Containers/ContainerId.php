<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\Internal\Exceptions\ContainerIdEmpty;
use TinyBlocks\DockerContainer\Internal\Exceptions\ContainerIdTooShort;

final readonly class ContainerId
{
    private const int CONTAINER_ID_LENGTH = 12;
    private const int CONTAINER_ID_OFFSET = 0;

    public string $value;

    private function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new ContainerIdEmpty();
        }

        if (strlen($trimmed) < self::CONTAINER_ID_LENGTH) {
            throw new ContainerIdTooShort(containerId: $trimmed, minimumLength: self::CONTAINER_ID_LENGTH);
        }

        $this->value = substr($trimmed, self::CONTAINER_ID_OFFSET, self::CONTAINER_ID_LENGTH);
    }

    public static function from(string $value): ContainerId
    {
        return new ContainerId(value: $value);
    }
}
