<?php

declare(strict_types=1);

use App\Dashboard\Input;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

/**
 * Give dash:watch an input that sends the given lines, then closes.
 *
 * @param  string  $lines  The lines rift-dash sends.
 *
 * @return void
 */
function watchInput(string $lines = ''): void
{
    $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    assert(is_array($pair));
    fwrite($pair[1], $lines);
    fclose($pair[1]);

    app()->instance(Input::class, new Input($pair[0]));
}

/**
 * Get the JSON lines a dash:watch run printed.
 *
 * @param  string  $output  The output.
 *
 * @return list<array<array-key, mixed>> The decoded snapshots.
 */
function snapshotsIn(string $output): array
{
    $snapshots = [];

    foreach (array_filter(explode("\n", trim($output))) as $line) {
        $decoded = json_decode($line, true);
        assert(is_array($decoded));
        $snapshots[] = $decoded;
    }

    return $snapshots;
}

describe('when run in a project until rift-dash closes its input', function (): void {
    beforeEach(function (): void {
        caddy([]);
        host(lifecycleProject());
    });

    it('prints a snapshot as one line of JSON', function (): void {
        watchInput();

        $snapshots = snapshotsIn(bufferedOutputOf('dash:watch'));

        expect($snapshots)->toHaveCount(1)
            ->and($snapshots[0]['version'] ?? null)->toBe(1)
            ->and(data_get($snapshots[0], 'projects.0.name'))->toBe('myapp');
    });

    it('prints another snapshot after each request', function (): void {
        watchInput("refresh\nanything\n");

        expect(snapshotsIn(bufferedOutputOf('dash:watch')))->toHaveCount(3);
    });

    it('reads git again after a refresh', function (): void {
        watchInput("refresh\n");

        bufferedOutputOf('dash:watch');

        Process::assertRanTimes(fn (PendingProcess $process) => is_array($process->command) && in_array('status', $process->command, true), 2);
    });

    it('keeps the git status it read between other requests', function (): void {
        watchInput("anything\n");

        bufferedOutputOf('dash:watch');

        Process::assertRanTimes(fn (PendingProcess $process) => is_array($process->command) && in_array('status', $process->command, true), 1);
    });

    it('exits with success', function (): void {
        watchInput();

        artisan('dash:watch')->assertExitCode(0);
    });
});

describe('when the project registry cannot be read', function (): void {
    beforeEach(function (): void {
        $root = lifecycleProject();
        mkdir($root . '/.config/rift', 0o777, true);
        file_put_contents($root . '/.config/rift/projects.yaml', "projects: nope\n");
        host($root);
        watchInput();
    });

    it('exits with failure', function (): void {
        artisan('dash:watch')->assertExitCode(1);
    });

    it('says what is wrong with it', function (): void {
        artisan('dash:watch')->expectsOutputToContain('`projects` must be a list of directories.');
    });

    it('prints no snapshot', function (): void {
        expect(bufferedOutputOf('dash:watch'))->not->toContain('"version"');
    });
});
