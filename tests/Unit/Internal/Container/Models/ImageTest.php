<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Container\Models;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ImageTest extends TestCase
{
    public function testExceptionWhenImageNameIsEmpty(): void
    {
        /** @Given an empty value */
        $value = '';

        /** @Then an InvalidArgumentException should be thrown */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Image name cannot be empty.');

        /** @When the image name is created with the empty value */
        Image::from(image: $value);
    }
}
