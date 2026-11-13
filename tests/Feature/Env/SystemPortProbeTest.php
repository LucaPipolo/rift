<?php

declare(strict_types=1);

use App\Env\SystemPortProbe;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

describe('isListening()', function (): void {
    it('returns true when lsof finds a listener on the port', function (): void {
        Process::fake(['*lsof*' => Process::result("node 123 luca 20u IPv4 TCP *:5173 (LISTEN)\n")]);

        expect(new SystemPortProbe()->isListening(5173))->toBeTrue();
    });

    it('returns false when lsof finds no listener on the port', function (): void {
        Process::fake(['*lsof*' => Process::result('', '', 1)]);

        expect(new SystemPortProbe()->isListening(5173))->toBeFalse();
    });

    it('asks lsof about TCP listeners on the port', function (): void {
        Process::fake(['*lsof*' => Process::result('', '', 1)]);

        new SystemPortProbe()->isListening(5173);

        Process::assertRan(['lsof', '-nP', '-iTCP:5173', '-sTCP:LISTEN']);
    });
});

describe('listener()', function (): void {
    it('returns the name of the process listening on the port', function (): void {
        Process::fake(['*lsof*' => Process::result("p123\ncnginx\n")]);

        expect(new SystemPortProbe()->listener(443))->toBe('nginx');
    });

    it('returns null when lsof finds no listener on the port', function (): void {
        Process::fake(['*lsof*' => Process::result('', '', 1)]);

        expect(new SystemPortProbe()->listener(443))->toBeNull();
    });

    it('returns null when lsof names no process', function (): void {
        Process::fake(['*lsof*' => Process::result('')]);

        expect(new SystemPortProbe()->listener(443))->toBeNull();
    });

    it('asks lsof for the command of TCP listeners on the port', function (): void {
        Process::fake(['*lsof*' => Process::result('', '', 1)]);

        new SystemPortProbe()->listener(443);

        Process::assertRan(['lsof', '-nP', '-iTCP:443', '-sTCP:LISTEN', '-Fc']);
    });
});

describe('publishedBy()', function (): void {
    it('returns the host ports the project containers publish', function (): void {
        Process::fake(['*docker*' => Process::result(
            "0.0.0.0:5173->5173/tcp, [::]:5173->5173/tcp\n0.0.0.0:54320->5432/tcp\n80/tcp\n",
        )]);

        expect(new SystemPortProbe()->publishedBy('myapp'))->toBe([5173, 54320]);
    });

    it('returns no ports when docker fails', function (): void {
        Process::fake(['*docker*' => Process::result('', 'Cannot connect to the Docker daemon', 1)]);

        expect(new SystemPortProbe()->publishedBy('myapp'))->toBe([]);
    });

    it('filters the containers by the Compose project label', function (): void {
        Process::fake(['*docker*' => Process::result('')]);

        new SystemPortProbe()->publishedBy('myapp-feat-x');

        Process::assertRan(['docker', 'ps', '--filter', 'label=com.docker.compose.project=myapp-feat-x', '--format', '{{.Ports}}']);
    });

    it('asks docker only once per project', function (): void {
        Process::fake(['*docker*' => Process::result('0.0.0.0:5173->5173/tcp')]);
        $probe = new SystemPortProbe();

        $probe->publishedBy('myapp');
        $probe->publishedBy('myapp');

        Process::assertRanTimes(['docker', 'ps', '--filter', 'label=com.docker.compose.project=myapp', '--format', '{{.Ports}}'], 1);
    });

    it('asks docker separately for each project', function (): void {
        Process::fake(['*docker*' => Process::result('')]);
        $probe = new SystemPortProbe();

        $probe->publishedBy('myapp');
        $probe->publishedBy('other');

        Process::assertRanTimes(fn (PendingProcess $process): bool => ((array) $process->command)[0] === 'docker', 2);
    });
});
