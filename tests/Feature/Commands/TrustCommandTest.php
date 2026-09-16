<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;

describe('when Docker is not running', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*info*' => Process::result(exitCode: 1)]);
        routerWithScratchTrustStore();
    });

    it('explains that rift needs a running Docker daemon', function (): void {
        artisan('trust')->expectsOutputToContain('rift needs a running Docker daemon and could not reach one.');
    });

    it('exits with failure', function (): void {
        artisan('trust')->assertExitCode(1);
    });
});

describe('when the router is not running', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*ps*' => '']);
        routerWithScratchTrustStore();
    });

    it('asks to start the router first', function (): void {
        artisan('trust')
            ->expectsOutputToContain('rift-router is not running. Run `rift router:start` first.');
    });

    it('exits with failure', function (): void {
        artisan('trust')->assertExitCode(1);
    });
});

describe('when the CA never appears', function (): void {
    beforeEach(function (): void {
        Sleep::fake();
        host(project(), ['*docker*exec*' => Process::result(exitCode: 1)]);
        routerWithScratchTrustStore();
    });

    it('says the CA never appeared', function (): void {
        artisan('trust')->expectsOutputToContain('never appeared');
    });

    it('does not try to trust the CA', function (): void {
        artisan('trust')->doesntExpectOutputToContain('Trusting CA certificate');
    });

    it('exits with failure', function (): void {
        artisan('trust')->assertExitCode(1);
    });
});

describe('when the CA cannot be read', function (): void {
    beforeEach(function (): void {
        host(project(), ['*docker*cp*' => Process::result(exitCode: 1)]);
        routerWithScratchTrustStore();
    });

    it('says it could not read the CA', function (): void {
        artisan('trust')->expectsOutputToContain('could not read the CA');
    });

    it('exits with failure', function (): void {
        artisan('trust')->assertExitCode(1);
    });
});

describe('when the CA is already trusted', function (): void {
    beforeEach(function (): void {
        host(project(), opensslReadsCaFingerprint());
        routerWithTrustedCa();
    });

    it('skips trusting the CA again', function (): void {
        artisan('trust')->expectsOutputToContain('already trusted');
    });

    it('does not ask for sudo', function (): void {
        artisan('trust')->run();

        Process::assertNotRan(fn (PendingProcess $process): bool => ($process->command[0] ?? null) === 'sudo');
    });

    it('exits with success', function (): void {
        artisan('trust')->assertExitCode(0);
    });
});

describe('when the CA is not trusted yet', function (): void {
    beforeEach(function (): void {
        host(project());
        $this->trustDirectory = routerWithScratchTrustStore();
    });

    it('opens with the router name', function (): void {
        artisan('trust')->expectsOutputToContain('rift-router');
    });

    it('copies the CA into the system trust store', function (): void {
        artisan('trust')->run();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command
            === ['sudo', 'cp', getenv('XDG_CONFIG_HOME') . '/rift/rift-router-ca.crt', $this->trustDirectory . '/rift-router-ca.crt']);
    });

    it('refreshes the system trust store', function (): void {
        artisan('trust')->run();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['sudo', 'update-ca-certificates']);
    });

    it('says the CA is trusted', function (): void {
        artisan('trust')->expectsOutputToContain('✓  trusted');
    });

    it('exits with success', function (): void {
        artisan('trust')->assertExitCode(0);
    });
});

describe('when the system refuses to trust the CA', function (): void {
    beforeEach(function (): void {
        host(project(), ['*sudo*cp*' => Process::result(exitCode: 1)]);
        routerWithScratchTrustStore();
    });

    it('says it could not trust the CA', function (): void {
        artisan('trust')->expectsOutputToContain('could not trust');
    });

    it('exits with failure', function (): void {
        artisan('trust')->assertExitCode(1);
    });
});
