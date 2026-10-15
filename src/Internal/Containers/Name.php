<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\Ksuid\Ksuid;

final readonly class Name
{
    private function __construct(public string $value)
    {
    }

    public static function from(string $value): Name
    {
        return new Name(value: $value);
    }

    public static function generated(): Name
    {
        return new Name(value: Ksuid::random()->value());
    }
}
