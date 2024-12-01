<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Models;

use TinyBlocks\Ksuid\Ksuid;

final readonly class Name
{
    private function __construct(public string $value)
    {
    }

    public static function from(?string $value): Name
    {
        $value = empty($value) ? Ksuid::random()->getValue() : $value;

        return new Name(value: $value);
    }
}
