<?php

declare(strict_types=1);

namespace Tests;

use App\Commands\RiftCommand;
use Closure;
use Laravel\Prompts\Prompt;
use LaravelZero\Framework\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private string $workingDirectory;

    /**
     * Remember the working directory and reset state commands keep between runs.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->workingDirectory = (string) getcwd();

        // Once a command switches Prompts to its console fallback, it stays on,
        // so reset it to let every test start as a fresh run of the app would.
        Prompt::flushState();

        // The banner flag is static so `setup` handing over to `up` prints one
        // banner, which would otherwise leak from one test into the next.
        Closure::bind(static function (): void {
            RiftCommand::$headerShown = false;
        }, null, RiftCommand::class)();
    }

    /**
     * Restore the working directory and the environment a test may have changed.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        chdir($this->workingDirectory);
        putenv('XDG_CONFIG_HOME');

        parent::tearDown();
    }
}
