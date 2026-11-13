<?php

declare(strict_types=1);

use App\Contracts\Terminal;
use App\Dashboard\Companion;
use App\Dashboard\Rift;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

/**
 * Put an executable rift-dash in a scratch directory and make rift find it there.
 *
 * @return string The rift-dash path.
 */
function companionInstalled(): string
{
    $directory = scratch();
    $path = $directory . '/rift-dash';
    file_put_contents($path, "#!/bin/sh\n");
    chmod($path, 0o755);

    app()->instance(Companion::class, new Companion(new Rift('cli', '/usr/bin/php', '/nowhere/rift'), $directory));
    app()->instance(Rift::class, new Rift('cli', '/usr/bin/php', '/app/rift'));

    return $path;
}

describe('when run at a terminal with rift-dash installed', function (): void {
    beforeEach(function (): void {
        app()->instance(Terminal::class, fakeTerminal());
        $this->companion = companionInstalled();
        host(lifecycleProject(), ['*rift-dash*' => Process::result(exitCode: 0)]);
    });

    it('hands the terminal to rift-dash', function (): void {
        artisan('dash')->run();

        Process::assertRan(fn (PendingProcess $process) => $process->command === [$this->companion] && $process->timeout === null);
    });

    it('tells rift-dash how to run this rift', function (): void {
        artisan('dash')->run();

        Process::assertRan(fn (PendingProcess $process) => $process->command === [$this->companion]
            && $process->environment === ['RIFT_COMMAND' => '["/usr/bin/php","/app/rift"]']);
    });

    it('exits as rift-dash does', function (): void {
        artisan('dash')->assertExitCode(0);
    });
});

describe('when rift-dash fails', function (): void {
    it('exits with its exit code', function (): void {
        app()->instance(Terminal::class, fakeTerminal());
        companionInstalled();
        host(lifecycleProject(), ['*rift-dash*' => Process::result(exitCode: 3)]);

        artisan('dash')->assertExitCode(3);
    });
});

describe('when rift-dash is not installed', function (): void {
    beforeEach(function (): void {
        app()->instance(Terminal::class, fakeTerminal());
        app()->instance(Companion::class, new Companion(new Rift('cli', '/usr/bin/php', '/nowhere/rift'), scratch()));
        host(lifecycleProject());
    });

    it('exits with failure', function (): void {
        artisan('dash')->assertExitCode(1);
    });

    it('says how to get it', function (): void {
        artisan('dash')->expectsOutputToContain('Reinstall with `brew reinstall rift`, or run `make build` from source.');
    });
});

describe('when the output is not a terminal', function (): void {
    beforeEach(function (): void {
        app()->instance(Terminal::class, fakeTerminal(interactive: false));
        host(lifecycleProject());
        caddy([]);
    });

    it('says why it shows the status instead', function (): void {
        artisan('dash')->expectsOutputToContain('rift dash needs a terminal, so here is `rift status` instead.');
    });

    it('shows the status', function (): void {
        artisan('dash')->expectsOutputToContain('rift-router');
    });
});

describe('when Docker is not running', function (): void {
    it('exits with failure', function (): void {
        app()->instance(Terminal::class, fakeTerminal());
        host(lifecycleProject(), ['*docker*info*' => Process::result(exitCode: 1)]);

        artisan('dash')->assertExitCode(1);
    });
});
