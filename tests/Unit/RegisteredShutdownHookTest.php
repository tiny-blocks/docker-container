<?php

declare(strict_types=1);

namespace Test\Unit;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Internal\Containers\RegisteredShutdownHook;

final class RegisteredShutdownHookTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testRegisterWhenCallbackGivenThenCallbackIsCapturedForShutdown(): void
    {
        $template = '%s/Internal/Containers/Overrides/register_shutdown_function_spy.php';
        require_once sprintf($template, __DIR__);

        /** @Given a callback to run on process shutdown */
        $callback = static fn(): bool => true;

        /** @And a registered shutdown hook */
        $shutdownHook = new RegisteredShutdownHook();

        /** @When the callback is registered */
        $shutdownHook->register(callback: $callback);

        /** @Then the callback is captured for shutdown exactly once */
        self::assertSame([$callback], $GLOBALS['registeredShutdownCallbacks']);
    }
}
