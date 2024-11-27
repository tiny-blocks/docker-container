<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Container\Models;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ContainerIdTest extends TestCase
{
    public function testExceptionWhenIdIsEmpty(): void
    {
        /** @Given an empty value */
        $value = '';

        /** @Then an InvalidArgumentException should be thrown */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Container ID cannot be empty.');

        /** @When the container ID is created with the empty value */
        ContainerId::from(value: $value);
    }

    public function testExceptionWhenIdIsTooShort(): void
    {
        /** @Given a value with less than 12 characters */
        $value = 'abc123';

        /** @Then an InvalidArgumentException should be thrown */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Container ID <abc123> is too short. Minimum length is <12> characters.');

        /** @When the container ID is created with the short value */
        ContainerId::from(value: $value);
    }

    public function testContainerIdIsAcceptedWhenExactly12Characters(): void
    {
        /** @Given a value with exactly 12 characters */
        $value = 'abc123abc123';

        /** @When the container ID is created */
        $containerId = ContainerId::from(value: $value);

        /** @Then the container ID should be the same as the input value */
        $this->assertSame('abc123abc123', $containerId->value);
    }

    public function testContainerIdIsTruncatedIfLongerThan12Characters(): void
    {
        /** @Given a value with more than 12 characters */
        $value = 'abc123abc123abc123';

        /** @When the container ID is created */
        $containerId = ContainerId::from(value: $value);

        /** @Then the container ID should be truncated to 12 characters */
        $this->assertSame('abc123abc123', $containerId->value);
    }
}
