<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Exceptions;

use RuntimeException;

/**
 * Raised when a container run to completion exits with a non-zero status.
 */
final class ContainerExitUnsuccessful extends RuntimeException
{
    public function __construct(string $name, string $output)
    {
        $template = 'Container <%s> did not exit successfully. Output: <%s>.';

        parent::__construct(message: sprintf($template, $name, trim($output)));
    }
}
