<?php

declare(strict_types=1);

namespace Test\Unit\Internal\Containers;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Internal\Containers\ShutdownHook;

final class ShutdownHookTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testRegisterDelegatesToNativeShutdownFunction(): void
    {
        require_once __DIR__ . '/Overrides/register_shutdown_function_spy.php';

        /** @Given a ShutdownHook and a callback */
        $hook = new ShutdownHook();
        $callbackExecuted = false;
        $callback = static function () use (&$callbackExecuted): void {
            $callbackExecuted = true;
        };

        /** @When register is called */
        $hook->register(callback: $callback);

        /** @Then the callback should have been captured by the shutdown function */
        global $registeredShutdownCallbacks;
        self::assertCount(expectedCount: 1, haystack: $registeredShutdownCallbacks);

        /** @And the registered callback should be executable */
        ($registeredShutdownCallbacks[0])();
        self::assertTrue($callbackExecuted);
    }
}

